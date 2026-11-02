<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\AssignmentEndReason;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AdvancedRequestWithdrawn;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssignmentRecorder;
use App\Services\Assessments\CompletionRequestCloser;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeRequestCloser;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class WithdrawAdvancedRequest
{
    public function __construct(
        private StaffActionReason $reason,
        private AssessmentRowLock $rowLock,
        private AssignmentRecorder $assignments,
        private CompletionRequestCloser $completionRequests,
        private AssessmentStateMachine $stateMachine,
        private CommitteeRequestCloser $committee,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, string $reason): Assessment
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $assessment): Assessment {
            $locked = $this->rowLock->lock($assessment->id);
            $previous = $locked->status;

            if (! $previous instanceof AdvancedAssessmentStatus || ! $previous->isWithdrawable()) {
                throw IllegalStateTransition::from((string) $previous->value, AssessmentVerb::Withdraw->value);
            }

            $assessorId = $this->assignments->endActive($locked, AssignmentEndReason::Withdrawn)?->assessor_user_id;
            $this->completionRequests->cancelOpen($locked);
            $this->committee->closeOnWithdrawal($locked);
            $locked->withdrawn_at = Date::now();
            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::Withdrawn, AssessmentVerb::Withdraw, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AwardWithdrawn, $locked, ['status' => $previous->value], [
                'status' => AdvancedAssessmentStatus::Withdrawn->value,
                'frozen' => $locked->isFrozen(),
            ]));

            AdvancedRequestWithdrawn::dispatch($locked, $assessorId, $actor->id);

            return $locked;
        });
    }
}
