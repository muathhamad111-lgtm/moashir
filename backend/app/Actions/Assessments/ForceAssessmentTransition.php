<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Contracts\Assessments\AssessmentReopenStep;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\ForcedStateAligner;
use App\Services\Assessments\OverrideGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ForceAssessmentTransition
{
    public function __construct(
        private OverrideGate $gate,
        private ForcedStateAligner $aligner,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
        private AssessmentReopenStep $reopenStep,
    ) {}

    public function handle(User $actor, Assessment $assessment, AccreditedAssessmentStatus $target, string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $target, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, $reason);
            $previous = $locked->status;

            if (! $previous instanceof AccreditedAssessmentStatus || ! $this->mayForce($previous, $target)) {
                throw IllegalStateTransition::from((string) $previous->value, AssessmentVerb::ForceTransition->value);
            }

            $this->aligner->alignTo($locked, $target);
            $this->stateMachine->override($locked, $target, AssessmentVerb::ForceTransition, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentTransitionForced, $locked, ['status' => $previous->value], ['status' => $target->value]));

            if ($previous === AccreditedAssessmentStatus::Completed) {
                $this->reopenStep->onReopen($locked, $reason);
            }

            return $locked;
        });
    }

    private function mayForce(AccreditedAssessmentStatus $previous, AccreditedAssessmentStatus $target): bool
    {
        return $previous !== $target
            && $target->isForceTarget()
            && ! in_array($previous, [AccreditedAssessmentStatus::AwaitingPayment, AccreditedAssessmentStatus::Refunded, AccreditedAssessmentStatus::DeclinedIncomplete], true);
    }
}
