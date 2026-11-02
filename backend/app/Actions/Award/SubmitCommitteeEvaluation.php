<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Events\Award\CommitteeEvaluationSubmitted;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\ScoringEngine;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeProgressSync;
use App\Services\Award\CommitteeScoreReader;
use App\Services\Award\CommitteeWorkStarter;
use App\Services\Award\CommitteeWriteGate;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SubmitCommitteeEvaluation
{
    public function __construct(
        private CommitteeWriteGate $gate,
        private CommitteeWorkStarter $starter,
        private ScoringTreeBuilder $trees,
        private CommitteeScoreReader $scores,
        private ScoringEngine $engine,
        private CommitteeProgressSync $progress,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, CommitteeAssignment $assignment, ?string $reason): CommitteeAssignment
    {
        return DB::transaction(function () use ($actor, $assignment, $reason): CommitteeAssignment {
            $locked = $this->gate->enter($actor, $assignment, 'submit_committee', $reason);
            $assessment = $locked->assessment;
            $tree = $this->trees->build($assessment->criteriaVersion);
            $sheet = $this->scores->read($locked, $tree, $assessment->scale_kind);
            $missing = $tree->unscoredUnits($sheet);

            if ($missing !== []) {
                throw AssessmentRejected::scoresIncomplete($missing);
            }

            $this->starter->begin($locked, $actor->id);
            $result = $this->engine->compute($tree, $sheet);
            $previous = $locked->status;
            $locked->forceFill([
                'status' => CommitteeAssignmentStatus::Completed,
                'submitted_at' => Date::now(),
                'percent' => $result->percent,
                'total_score' => $result->total,
            ])->save();
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeSubmitted, $locked, ['status' => $previous->value], [
                'status' => CommitteeAssignmentStatus::Completed->value,
                'percent' => $result->percent->toDecimal(),
                'total' => $result->total->toDecimal(),
            ]));

            CommitteeEvaluationSubmitted::dispatch($locked);
            $this->progress->sync($assessment, $actor->id);

            return $locked;
        });
    }
}
