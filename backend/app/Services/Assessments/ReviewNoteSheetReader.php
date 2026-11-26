<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentReviewNote;
use App\Models\Identity\User;

final class ReviewNoteSheetReader
{
    /**
     * @return list<AssessmentReviewNote>
     */
    public function current(Assessment $assessment): array
    {
        $notes = AssessmentReviewNote::query()->with('author')->where('assessment_id', $assessment->id)->current()->get()->all();

        usort($notes, static fn (AssessmentReviewNote $first, AssessmentReviewNote $second): int => self::orderOf($first) <=> self::orderOf($second));

        return $notes;
    }

    /**
     * @return array<string, AssessmentReviewNote>
     */
    public function currentByUnitOf(Assessment $assessment, User $author): array
    {
        $notes = [];

        foreach (AssessmentReviewNote::query()->where('assessment_id', $assessment->id)->where('author_user_id', $author->id)->current()->get() as $note) {
            $notes[$note->unit()->key()] = $note;
        }

        return $notes;
    }

    /**
     * @return list<int>
     */
    private static function orderOf(AssessmentReviewNote $note): array
    {
        return [
            $note->level->rank(),
            $note->main_criterion_id,
            $note->sub_criterion_id ?? 0,
            $note->guiding_point_id ?? 0,
            $note->created_at->getTimestamp(),
            $note->id,
        ];
    }
}
