<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\CompletionRequestStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

final class CurrentCompletionRound
{
    /**
     * The completion request whose resolution opened the round now under review; null in the first round.
     */
    public function requestFor(Assessment $assessment): ?AssessmentCompletionRequest
    {
        return AssessmentCompletionRequest::query()
            ->where('assessment_id', $assessment->id)
            ->whereIn('status', array_map(static fn (CompletionRequestStatus $status): string => $status->value, CompletionRequestStatus::roundOpeners()))
            ->whereNotNull('resolved_at')
            ->orderByDesc('id')
            ->first();
    }

    public function idFor(Assessment $assessment): ?int
    {
        return $this->requestFor($assessment)?->id;
    }
}
