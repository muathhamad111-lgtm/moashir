<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\CompletionRequestStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Identity\User;

final readonly class CompletionRequestRecorder
{
    public function __construct(
        private BoundSubCriteriaReader $subs,
        private CompletionItemsResolver $items,
        private InitialReviewSla $sla,
    ) {}

    public function record(User $actor, Assessment $locked, CompletionInstruction $instruction): AssessmentCompletionRequest
    {
        $resumesTo = $this->resumePointOf($locked);
        $request = AssessmentCompletionRequest::query()->create([
            'assessment_id' => $locked->id,
            'notes' => $instruction->notes,
            'items' => $this->items->resolve($this->subs->of($locked->criteriaVersion), $instruction),
            'deadline_at' => $instruction->deadlineAt,
            'expiry_outcome' => $instruction->expiryOutcome,
            'status' => CompletionRequestStatus::Open,
            'requested_by_user_id' => $actor->id,
            'resumes_to' => $resumesTo,
            'review_days_remaining' => $resumesTo instanceof AdvancedAssessmentStatus ? $this->sla->remainingDaysOf($locked) : null,
        ]);
        $locked->setRelation('openCompletionRequest', $request);

        return $request;
    }

    private function resumePointOf(Assessment $locked): ?AdvancedAssessmentStatus
    {
        return $locked->status instanceof AdvancedAssessmentStatus && $locked->status->hasAssessor() ? $locked->status : null;
    }
}
