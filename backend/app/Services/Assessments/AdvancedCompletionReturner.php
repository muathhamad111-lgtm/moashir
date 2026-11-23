<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AdvancedReturnedForCompletion;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;

final readonly class AdvancedCompletionReturner
{
    public function __construct(
        private CompletionRequestRecorder $recorder,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function returnToEntity(User $actor, Assessment $locked, CompletionInstruction $instruction, AssessmentVerb $verb): Assessment
    {
        $this->assertBeforeCommitteeStart($locked, $instruction);

        $origin = (string) $locked->status->value;
        $request = $this->recorder->record($actor, $locked, $instruction);

        $this->stateMachine->transition($locked, AdvancedAssessmentStatus::NeedsCompletion, $verb, $actor->id);
        $this->audit->record(new AuditEntry(AuditEvent::AwardReturnedForCompletion, $locked, ['status' => $origin], [
            'status' => AdvancedAssessmentStatus::NeedsCompletion->value,
            'completion_request_id' => $request->id,
            'item_count' => count($request->items),
            'deadline_at' => $request->deadline_at->toIso8601String(),
            'expiry_outcome' => $request->expiry_outcome->value,
            'resumes_to' => $request->resumes_to?->value,
            'review_days_remaining' => $request->review_days_remaining,
        ]));

        AdvancedReturnedForCompletion::dispatch($locked, $actor->id);

        return $locked;
    }

    private function assertBeforeCommitteeStart(Assessment $locked, CompletionInstruction $instruction): void
    {
        $cycle = $locked->loadMissing('awardCycle')->awardCycle;

        if ($cycle instanceof AwardCycle && $instruction->deadlineAt->greaterThan($cycle->committee_start)) {
            throw AwardRejected::deadlineAfterCommitteeStart($cycle->committee_start->toIso8601String());
        }
    }
}
