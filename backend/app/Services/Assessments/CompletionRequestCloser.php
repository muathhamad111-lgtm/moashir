<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\CompletionExpiryOutcome;
use App\Enums\Assessments\CompletionRequestStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use Illuminate\Support\Facades\Date;

final class CompletionRequestCloser
{
    public function resolve(Assessment $locked): ?AssessmentCompletionRequest
    {
        return $this->close($locked, CompletionRequestStatus::Resubmitted);
    }

    public function cancelOpen(Assessment $locked): ?AssessmentCompletionRequest
    {
        return $this->close($locked, CompletionRequestStatus::Cancelled);
    }

    public function expire(Assessment $locked, CompletionExpiryOutcome $outcome): ?AssessmentCompletionRequest
    {
        return $this->close($locked, $outcome->requestStatus());
    }

    private function close(Assessment $locked, CompletionRequestStatus $status): ?AssessmentCompletionRequest
    {
        $open = AssessmentCompletionRequest::query()->where('assessment_id', $locked->id)->open()->first();

        if (! $open instanceof AssessmentCompletionRequest) {
            return null;
        }

        $open->forceFill(['status' => $status, 'resolved_at' => Date::now()])->save();
        $locked->setRelation('openCompletionRequest', null);

        return $open;
    }
}
