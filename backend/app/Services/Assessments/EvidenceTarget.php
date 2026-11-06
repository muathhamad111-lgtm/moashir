<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\EvidenceUpload;
use App\Enums\Assessments\CompletionItemKind;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;

final readonly class EvidenceTarget
{
    public const string SUB_FIELD = 'sub_criterion_id';

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

    public function resolve(Assessment $assessment, EvidenceUpload $upload): BoundSub
    {
        $sub = $this->subsOf($assessment)->withId($upload->subId) ?? throw AssessmentRejected::subNotInVersion(self::SUB_FIELD);

        if ($upload->attachmentKey !== null && ! $sub->hasSlot($upload->attachmentKey)) {
            throw AssessmentRejected::attachmentKeyInvalid(self::KEY_FIELD);
        }

        $this->completion->assertRequested($assessment, $sub->code, CompletionItemKind::Evidence, self::SUB_FIELD);
        $this->assertRoomFor($assessment, $sub);

        return $sub;
    }

    private function assertRoomFor(Assessment $assessment, BoundSub $sub): void
    {
        $maximum = $this->limits->read($assessment->path)->maxFilesPerSub;
        $present = EvidenceFile::query()->where('assessment_id', $assessment->id)->where('sub_code', $sub->code)->present()->count();

        if ($present >= $maximum) {
            throw AssessmentRejected::subEvidenceFull(self::SUB_FIELD, $maximum);
        }
    }
}
