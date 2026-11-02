<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AdvancedRequestResubmitted;
use App\Events\Assessments\AdvancedRequestSubmitted;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Assessments\CompletionRequestCloser;
use App\Services\Assessments\EntityContentGate;
use App\Services\Assessments\SubmissionGapsReader;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AdvancedEligibilityChecker;
use App\Services\Award\ConditionFailureMessages;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SubmitAdvancedRequest
{
    public function __construct(
        private EntityContentGate $gate,
        private BoundSubCriteriaReader $subs,
        private SubmissionGapsReader $gaps,
        private AdvancedEligibilityChecker $eligibility,
        private ConditionFailureMessages $messages,
        private AssessmentStateMachine $stateMachine,
        private CompletionRequestCloser $completionRequests,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, $reason);
            $gaps = $this->gaps->read($locked, $this->subs->of($locked->criteriaVersion));

            if (! $gaps->isEmpty()) {
                throw AssessmentRejected::submissionIncomplete($gaps->fieldErrors(), $gaps->toArray());
            }

            return $locked->status === AdvancedAssessmentStatus::Draft ? $this->submit($actor, $locked) : $this->resubmit($actor, $locked);
        });
    }

    private function submit(User $actor, Assessment $locked): Assessment
    {
        $cycle = $locked->loadMissing('awardCycle.regions')->awardCycle ?? throw AwardRejected::submissionClosed();
        $this->assertConditionsStillMet($locked, $cycle);

        $locked->forceFill(['submitted_at' => Date::now(), 'region_id' => $locked->entity->region_id]);
        $this->stateMachine->transition($locked, AdvancedAssessmentStatus::AwaitingReviewAssignment, AssessmentVerb::Submit, $actor->id);
        $this->audit->record(new AuditEntry(AuditEvent::AwardSubmitted, $locked, ['status' => AdvancedAssessmentStatus::Draft->value], [
            'status' => AdvancedAssessmentStatus::AwaitingReviewAssignment->value,
            'award_cycle_id' => $cycle->id,
            'region_id' => $locked->region_id,
        ]));

        AdvancedRequestSubmitted::dispatch($locked, $actor->id);

        return $locked;
    }

    private function resubmit(User $actor, Assessment $locked): Assessment
    {
        $this->stateMachine->transition($locked, AdvancedAssessmentStatus::AwaitingReviewAssignment, AssessmentVerb::Resubmit, $actor->id);
        $resolved = $this->completionRequests->resolve($locked);
        $this->audit->record(new AuditEntry(AuditEvent::AwardResubmitted, $locked, ['status' => AdvancedAssessmentStatus::NeedsCompletion->value], [
            'status' => AdvancedAssessmentStatus::AwaitingReviewAssignment->value,
            'completion_request_id' => $resolved?->id,
        ]));

        AdvancedRequestResubmitted::dispatch($locked, $actor->id);

        return $locked;
    }

    private function assertConditionsStillMet(Assessment $locked, AwardCycle $cycle): void
    {
        $eligibility = $this->eligibility->check($locked->entity, $cycle, Date::now());

        if (! $eligibility->meetsConditions()) {
            throw AwardRejected::conditionsFailed($this->messages->fieldErrors($eligibility), $eligibility->failuresToArray());
        }
    }
}
