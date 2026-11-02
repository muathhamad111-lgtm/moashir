<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\FilteringInstruction;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\FilteringOutcome;
use App\Events\Award\EntityQualified;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\FilteringDecisionRecorder;
use App\Services\Award\FilteringGate;
use App\Support\ValueObjects\Percent;
use Illuminate\Support\Facades\DB;

final readonly class QualifyAdvancedRequest
{
    public function __construct(
        private FilteringGate $gate,
        private FilteringDecisionRecorder $decisions,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, FilteringInstruction $instruction): Assessment
    {
        return DB::transaction(function () use ($actor, $cycle, $assessment, $instruction): Assessment {
            $next = AdvancedAssessmentStatus::QualifiedForCommittee;
            $locked = $this->gate->enter($actor, $cycle, $assessment, $next, AssessmentVerb::Qualify, $instruction->reason ?? $instruction->decisionReason);
            $lockedCycle = $locked->awardCycle ?? $cycle;
            $initial = $locked->currentResult instanceof AssessmentResultSnapshot ? $locked->currentResult->percent : Percent::zero();
            $below = $this->decisions->isBelow($initial, $lockedCycle);

            if ($below && $instruction->decisionReason === null) {
                throw AwardRejected::overrideReasonRequired($initial->toDisplay(), $lockedCycle->disqualification_score->toDisplay());
            }

            $decision = $this->decisions->record($actor, $locked, $lockedCycle, FilteringOutcome::Qualified, $below ? $instruction->decisionReason : null, $instruction->note);
            $this->stateMachine->transition($locked, $next, AssessmentVerb::Qualify, $actor->id);
            $this->audit->record(new AuditEntry($below ? AuditEvent::AwardQualifiedOverride : AuditEvent::AwardQualified, $locked, ['status' => AdvancedAssessmentStatus::AwaitingFiltering->value], [
                'status' => $next->value,
                'filtering_decision_id' => $decision->id,
                'initial_percent' => $decision->initial_percent->toDisplay(),
                'threshold_percent' => $decision->threshold_percent->toDisplay(),
                'is_override' => $decision->is_override,
            ]));

            EntityQualified::dispatch($locked, $decision, $actor->id);

            return $locked;
        });
    }
}
