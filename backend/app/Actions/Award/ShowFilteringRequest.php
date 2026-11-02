<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Actions\Assessments\GetAssessorReview;
use App\Data\Award\FilteringDetail;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Award\FilteringDecision;
use App\Models\Identity\User;
use App\Services\Assessments\InitialResultReader;

final readonly class ShowFilteringRequest
{
    public function __construct(private GetAssessorReview $review, private InitialResultReader $results) {}

    public function handle(User $viewer, Assessment $assessment): FilteringDetail
    {
        $assessment->loadMissing(['awardCycle', 'region']);
        $assignment = AssessmentAssignment::query()->with('assessor')->where('assessment_id', $assessment->id)->latest('id')->first();

        return new FilteringDetail(
            $this->review->handle($viewer, $assessment),
            $this->results->read($assessment),
            $assignment?->assessor,
            array_values(FilteringDecision::query()->with('decidedBy')->where('assessment_id', $assessment->id)->orderBy('id')->get()->all()),
        );
    }
}
