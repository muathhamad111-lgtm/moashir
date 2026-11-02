<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\CompletionRequestStatus;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AdvancedReturnedForCompletion;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Assessments\CompletionItemsResolver;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReturnAdvancedForCompletion
{
    public function __construct(
        private ReviewWriteGate $gate,
        private BoundSubCriteriaReader $subs,
        private CompletionItemsResolver $items,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, CompletionInstruction $instruction): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $instruction): Assessment {
            $locked = $this->gate->enter($actor, $assessment, AdvancedAssessmentStatus::NeedsCompletion, AssessmentVerb::ReturnForCompletion, $instruction->reason);

            if ($locked->status !== AdvancedAssessmentStatus::AwaitingReviewAssignment) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::ReturnForCompletion->value);
            }

            $this->assertBeforeCommitteeStart($locked, $instruction);
            $request = AssessmentCompletionRequest::query()->create([
                'assessment_id' => $locked->id,
                'notes' => $instruction->notes,
                'items' => $this->items->resolve($this->subs->of($locked->criteriaVersion), $instruction),
                'deadline_at' => $instruction->deadlineAt,
                'expiry_outcome' => $instruction->expiryOutcome,
                'status' => CompletionRequestStatus::Open,
                'requested_by_user_id' => $actor->id,
            ]);
            $locked->setRelation('openCompletionRequest', $request);

            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::NeedsCompletion, AssessmentVerb::ReturnForCompletion, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AwardReturnedForCompletion, $locked, ['status' => AdvancedAssessmentStatus::AwaitingReviewAssignment->value], [
                'status' => AdvancedAssessmentStatus::NeedsCompletion->value,
                'completion_request_id' => $request->id,
                'item_count' => count($request->items),
                'deadline_at' => $request->deadline_at->toIso8601String(),
                'expiry_outcome' => $request->expiry_outcome->value,
            ]));

            AdvancedReturnedForCompletion::dispatch($locked, $actor->id);

            return $locked;
        });
    }

    private function assertBeforeCommitteeStart(Assessment $locked, CompletionInstruction $instruction): void
    {
        $cycle = $locked->loadMissing('awardCycle')->awardCycle;

        if ($cycle instanceof AwardCycle && $instruction->deadlineAt->greaterThan($cycle->committee_start)) {
            throw AwardRejected::deadlineAfterCommitteeStart($cycle->committee_start->toIso8601String());
        }
    }
}
