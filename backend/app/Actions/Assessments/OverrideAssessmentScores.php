<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\SubmittedScores;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\OverrideGate;
use App\Services\Assessments\ResultSnapshotRecorder;
use App\Services\Assessments\ScoreChangesRecorder;
use App\Services\Assessments\ScoringEngine;
use App\Services\Assessments\ScoringTreeBuilder;
use Illuminate\Support\Facades\DB;

final readonly class OverrideAssessmentScores
{
    public function __construct(
        private OverrideGate $gate,
        private ScoringTreeBuilder $trees,
        private ScoreChangesRecorder $scores,
        private ScoringEngine $engine,
        private ResultSnapshotRecorder $snapshots,
        private AssessmentStateMachine $stateMachine,
    ) {}

    public function handle(User $actor, Assessment $assessment, SubmittedScores $changes, string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $changes, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, $reason);

            if (! $locked->exposesResult()) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::OverrideScore->value);
            }

            $tree = $this->trees->build($locked->criteriaVersion);
            $recorded = $this->scores->record($actor, $locked, $tree, $changes, AuditEvent::AssessmentScoreOverridden);

            if ($recorded->changes->isEmpty()) {
                return $locked;
            }

            $this->snapshots->record($locked, $this->engine->compute($tree, $recorded->sheet), ResultSnapshotKind::OverrideRecompute, $actor->id);
            $locked->save();
            $this->stateMachine->override($locked, $locked->status, AssessmentVerb::OverrideScore, $actor->id);

            return $locked;
        });
    }
}
