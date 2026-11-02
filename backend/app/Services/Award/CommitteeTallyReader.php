<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CommitteeTally;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAssignment;

final class CommitteeTallyReader
{
    public function tally(Assessment $assessment): CommitteeTally
    {
        $live = CommitteeAssignment::query()->where('assessment_id', $assessment->id)->live()->orderBy('id')->get();
        $counted = [];
        $pendingSubstitutes = [];

        foreach ($live as $assignment) {
            if ($assignment->isPendingSubstitute()) {
                $pendingSubstitutes[] = $assignment;

                continue;
            }

            $counted[] = $assignment;
        }

        return new CommitteeTally($counted, $pendingSubstitutes);
    }
}
