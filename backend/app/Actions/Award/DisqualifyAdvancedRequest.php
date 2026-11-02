<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\FilteringInstruction;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\FilteringOutcome;
use App\Events\Award\EntityNotQualified;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\FilteringDecisionRecorder;
use App\Services\Award\FilteringGate;
use Illuminate\Support\Facades\DB;

final readonly class DisqualifyAdvancedRequest
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
            $next = AdvancedAssessmentStatus::NotQualified;
            $locked = $this->gate->enter($actor, $cycle, $assessment, $next, AssessmentVerb::Disqualify, $instruction->reason ?? $instruction->decisionReason);
            $decision = $this->decisions->record($actor, $locked, $locked->awardCycle ?? $cycle, FilteringOutcome::NotQualified, $instruction->decisionReason, $instruction->note);

            $this->stateMachine->transition($locked, $next, AssessmentVerb::Disqualify, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AwardDisqualified, $locked, ['status' => AdvancedAssessmentStatus::AwaitingFiltering->value], [
                'status' => $next->value,
                'filtering_decision_id' => $decision->id,
                'initial_percent' => $decision->initial_percent->toDisplay(),
                'threshold_percent' => $decision->threshold_percent->toDisplay(),
            ]));

            EntityNotQualified::dispatch($locked, $decision, $actor->id);

            return $locked;
        });
    }
}
