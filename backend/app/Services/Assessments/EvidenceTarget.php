<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\EvidenceNode;
use App\Data\Assessments\AttachmentSlot;
use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\EvidenceDestination;
use App\Data\Assessments\EvidenceNodeRef;
use App\Data\Assessments\EvidenceUpload;
use App\Enums\Assessments\CompletionItemKind;
use App\Enums\Assessments\EvidenceLevel;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;

/**
 * Checks where an upload may go: a criterion of the request's version, one of its slots, a requested item while the
 * request is back for completion, and room left in the criterion and in the slot.
 */
final readonly class EvidenceTarget
{
    public const string SUB_FIELD = 'sub_criterion_id';

    public const string MAIN_FIELD = 'main_criterion_id';

    public const string KEY_FIELD = 'attachment_key';

    public function __construct(
        private BoundSubCriteriaReader $subs,
        private CompletionScope $completion,
        private UploadLimitsReader $limits,
    ) {}

    public function subsOf(Assessment $assessment): BoundSubCriteria
    {
        return $this->subs->of($assessment->criteriaVersion);
    }

    public function resolve(Assessment $assessment, EvidenceUpload $upload): EvidenceDestination
    {
        $node = $this->nodeOf($this->subsOf($assessment), $upload->node);
        $slot = $this->slotOf($node, $upload->attachmentKey);
        $limits = $this->limits->read($assessment->path);

        $this->completion->assertRequested($assessment, $node->nodeCode(), CompletionItemKind::Evidence, $this->fieldOf($node->level()));
        $this->assertRoomIn($assessment, $node, $limits->maxFilesPerSub);

        if ($slot instanceof AttachmentSlot) {
            $this->assertRoomInSlot($assessment, $node, $slot, $limits->maxFilesPerSub);
        }

        return new EvidenceDestination($node, $slot, $slot?->typesWithin($limits->types) ?? $limits->types);
    }

    private function nodeOf(BoundSubCriteria $criteria, EvidenceNodeRef $ref): EvidenceNode
    {
        return $ref->level === EvidenceLevel::Main
            ? $criteria->mainWithId($ref->id) ?? throw AssessmentRejected::mainNotInVersion(self::MAIN_FIELD)
            : $criteria->withId($ref->id) ?? throw AssessmentRejected::subNotInVersion(self::SUB_FIELD);
    }

    /**
     * A main criterion takes files in its attachments only; a sub-criterion also takes additional evidence (no key).
     */
    private function slotOf(EvidenceNode $node, ?string $key): ?AttachmentSlot
    {
        if ($key === null && $node->level() === EvidenceLevel::Sub) {
            return null;
        }

        return $node->slot((string) $key) ?? throw AssessmentRejected::attachmentKeyInvalid(self::KEY_FIELD);
    }

    private function assertRoomIn(Assessment $assessment, EvidenceNode $node, int $maximum): void
    {
        if ($this->presentCount($assessment, $node) < $maximum) {
            return;
        }

        throw $node->level() === EvidenceLevel::Main
            ? AssessmentRejected::mainEvidenceFull(self::MAIN_FIELD, $maximum)
            : AssessmentRejected::subEvidenceFull(self::SUB_FIELD, $maximum);
    }

    private function assertRoomInSlot(Assessment $assessment, EvidenceNode $node, AttachmentSlot $slot, int $pathMaximum): void
    {
        $maximum = $slot->maxFilesWithin($pathMaximum);

        if ($this->presentCount($assessment, $node, $slot->key) >= $maximum) {
            throw AssessmentRejected::slotEvidenceFull(self::KEY_FIELD, $maximum);
        }
    }

    private function presentCount(Assessment $assessment, EvidenceNode $node, ?string $key = null): int
    {
        return EvidenceFile::query()
            ->where('assessment_id', $assessment->id)
            ->ofOwner($node->level(), $node->nodeCode())
            ->when($key !== null, static fn ($query) => $query->where('attachment_key', $key))
            ->present()
            ->count();
    }

    private function fieldOf(EvidenceLevel $level): string
    {
        return $level === EvidenceLevel::Main ? self::MAIN_FIELD : self::SUB_FIELD;
    }
}
