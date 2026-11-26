<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\CompletionRequested;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;

final readonly class AccreditedCompletionRequester
{
    public function __construct(
        private CompletionRequestRecorder $recorder,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function request(User $actor, Assessment $locked, CompletionInstruction $instruction): Assessment
    {
        $request = $this->recorder->record($actor, $locked, $instruction);

        $this->stateMachine->transition($locked, AccreditedAssessmentStatus::NeedsCompletion, AssessmentVerb::RequestCompletion, $actor->id);
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentCompletionRequested, $locked, ['status' => AccreditedAssessmentStatus::UnderReview->value], [
            'status' => AccreditedAssessmentStatus::NeedsCompletion->value,
            'completion_request_id' => $request->id,
            'item_count' => count($request->items),
            'deadline_at' => $request->deadline_at->toIso8601String(),
            'expiry_outcome' => $request->expiry_outcome->value,
        ]));

        CompletionRequested::dispatch($locked, $request);

        return $locked;
    }
}
