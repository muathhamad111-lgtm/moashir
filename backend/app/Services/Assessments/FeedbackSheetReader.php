<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\FeedbackSheet;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentFeedbackItem;
use Illuminate\Database\Eloquent\Collection;

final class FeedbackSheetReader
{
    public function read(Assessment $assessment): FeedbackSheet
    {
        $bodies = [];

        foreach ($this->currentRows($assessment) as $item) {
            $bodies[$item->sub_code][$item->kind->value] = $item->body;
        }

        return new FeedbackSheet($bodies);
    }

    /**
     * @return Collection<int, AssessmentFeedbackItem>
     */
    public function currentRows(Assessment $assessment): Collection
    {
        return AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->current()->orderBy('id')->get();
    }
}
