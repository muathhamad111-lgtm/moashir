<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\CompletionRequestStatus;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\CompletionRequested;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Assessments\CompletionItemsResolver;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RequestCompletion
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private BoundSubCriteriaReader $subs,
        private CompletionItemsResolver $items,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, CompletionInstruction $instruction): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $instruction): Assessment {
            $locked = $this->gate->enter($actor, $assessment, AccreditedAssessmentStatus::NeedsCompletion, AssessmentVerb::RequestCompletion, $instruction->reason);
            $this->assignee->assertActsOn($actor, $locked);

            if ($locked->status !== AccreditedAssessmentStatus::UnderReview) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::RequestCompletion->value);
            }

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
        });
    }
}
