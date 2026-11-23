<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ReviewNoteChange;
use App\Data\Assessments\ReviewNoteChanges;
use App\Data\Assessments\ReviewNoteStamp;
use App\Data\Assessments\ReviewNoteUnit;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentReviewNote;
use App\Models\Identity\User;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;

final readonly class ReviewNoteSheetWriter
{
    public function __construct(
        private ReviewNoteSheetReader $reader,
        private ReviewNoteAuthorRoleResolver $roles,
        private RequestContext $context,
    ) {}

    /**
     * @return list<ReviewNoteUnit>
     */
    public function write(Assessment $locked, ReviewNoteChanges $changes, User $author): array
    {
        $current = $this->reader->currentByUnitOf($locked, $author);
        $stamp = new ReviewNoteStamp($locked->id, $author->id, $this->roles->roleOf($author, $locked), $this->context->reason());
        $changed = [];

        foreach ($changes->entries as $change) {
            if ($this->apply($current[$change->unit->key()] ?? null, $change, $stamp)) {
                $changed[] = $change->unit;
            }
        }

        return $changed;
    }

    private function apply(?AssessmentReviewNote $current, ReviewNoteChange $change, ReviewNoteStamp $stamp): bool
    {
        $body = $change->trimmedBody();

        if (($current->body ?? '') === $body) {
            return false;
        }

        $current?->forceFill(['superseded_at' => Date::now()])->save();

        if ($body !== '') {
            AssessmentReviewNote::query()->create([
                'assessment_id' => $stamp->assessmentId,
                'level' => $change->unit->level,
                'main_criterion_id' => $change->unit->mainCriterionId,
                'sub_criterion_id' => $change->unit->subCriterionId,
                'guiding_point_id' => $change->unit->guidingPointId,
                'author_user_id' => $stamp->authorId,
                'author_role' => $stamp->role,
                'body' => $body,
                'version' => $this->nextVersion($change->unit, $stamp),
                'change_reason' => $stamp->reason,
            ]);
        }

        return true;
    }

    private function nextVersion(ReviewNoteUnit $unit, ReviewNoteStamp $stamp): int
    {
        return ScalarValue::int(AssessmentReviewNote::query()
            ->where('assessment_id', $stamp->assessmentId)
            ->where('author_user_id', $stamp->authorId)
            ->where('level', $unit->level)
            ->where('main_criterion_id', $unit->mainCriterionId)
            ->where('sub_criterion_id', $unit->subCriterionId)
            ->where('guiding_point_id', $unit->guidingPointId)
            ->max('version')) + 1;
    }
}
