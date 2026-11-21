<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CarriedScore;
use App\Data\Assessments\DesiredScores;
use App\Data\Assessments\RebindPlan;
use App\Data\Assessments\RevisionStamp;
use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoreChange;
use App\Data\Assessments\ScoreChangeSet;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoringTree;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Assessments\ScorerRole;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentScore;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use App\Support\ValueObjects\RawScore;
use Illuminate\Support\Facades\Date;

final readonly class ScoreSheetWriter
{
    public function __construct(private ScoreSheetReader $reader, private RevisionStamper $stamper) {}

    public function replace(Assessment $assessment, ScoringTree $tree, DesiredScores $desired, User $scorer, ScorerRole $role): ScoreChangeSet
    {
        $stamp = $this->stamper->stampFor($scorer, $assessment);
        $current = $this->currentByKey($assessment);
        $superseded = [];
        $cleared = [];

        foreach ($current as $key => $row) {
            $stored = $row->rawScore($tree->scoringTypeOf($row->level, $row->nodeId(), $assessment->scale_kind));

            if ($this->keeps($key, $row, $stored, $desired, $stamp)) {
                continue;
            }

            $superseded[] = $row;

            if (! $desired->sheet->rawForKey($key) instanceof RawScore) {
                $cleared[] = new ScoreChange($tree->unit($row->level, $row->nodeId())->code ?? $key, $stored, null);
            }
        }

        $this->supersede($superseded);

        return new ScoreChangeSet([...$cleared, ...$this->insertChanged($assessment, $current, $desired, $stamp, $role)]);
    }

    public function applyRebind(Assessment $assessment, RebindPlan $plan): void
    {
        $this->supersede([
            ...$plan->cleared,
            ...array_map(static fn (CarriedScore $carried): AssessmentScore => $carried->previous, $plan->carried),
        ]);

        foreach ($plan->carried as $carried) {
            AssessmentScore::query()->create([
                'assessment_id' => $assessment->id,
                ...$this->nodeColumns($carried->target),
                'scorer_user_id' => $carried->previous->scorer_user_id,
                'scorer_role' => $carried->previous->scorer_role,
                'raw_value' => $carried->previous->raw_value,
                'percent' => $carried->previous->percent,
                'version' => 1,
                'completion_request_id' => $carried->previous->completion_request_id,
            ]);
        }
    }

    /**
     * @return array<string, AssessmentScore>
     */
    private function currentByKey(Assessment $assessment): array
    {
        $current = [];

        foreach ($this->reader->currentRows($assessment) as $row) {
            $current[ScorableUnit::keyFor($row->level, $row->nodeId())] = $row;
        }

        return $current;
    }

    /**
     * @param  array<string, AssessmentScore>  $current
     * @return list<ScoreChange>
     */
    private function insertChanged(Assessment $assessment, array $current, DesiredScores $desired, RevisionStamp $stamp, ScorerRole $role): array
    {
        $versions = $this->latestVersions($assessment);
        $changes = [];

        foreach ($desired->sheet->entries() as $scored) {
            $key = $scored->unit->key();
            $row = $current[$key] ?? null;
            $before = $row?->rawScore($scored->raw->type);

            if ($row instanceof AssessmentScore && $before instanceof RawScore && $this->keeps($key, $row, $before, $desired, $stamp)) {
                continue;
            }

            $this->insert($assessment, $scored, $stamp, $role, ($versions[$key] ?? 0) + 1);
            $changes[] = new ScoreChange($scored->unit->code, $before, $scored->raw);
        }

        return $changes;
    }

    private function keeps(string $key, AssessmentScore $row, RawScore $stored, DesiredScores $desired, RevisionStamp $stamp): bool
    {
        $wanted = $desired->sheet->rawForKey($key);

        return $wanted instanceof RawScore
            && $wanted->equals($stored)
            && ! $desired->reconfirms($key, $row->completion_request_id, $stamp->completionRequestId);
    }

    /**
     * @param  list<AssessmentScore>  $rows
     */
    private function supersede(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        AssessmentScore::query()
            ->whereKey(array_map(static fn (AssessmentScore $row): int => $row->id, $rows))
            ->update(['superseded_at' => Date::now()]);
    }

    private function insert(Assessment $assessment, ScoredUnit $scored, RevisionStamp $stamp, ScorerRole $role, int $version): void
    {
        AssessmentScore::query()->create([
            'assessment_id' => $assessment->id,
            ...$this->nodeColumns($scored->unit),
            'scorer_user_id' => $stamp->authorId,
            'scorer_role' => $role,
            'raw_value' => $scored->raw->toDecimal(),
            'percent' => $scored->raw->percent(),
            'version' => $version,
            'change_reason' => $stamp->reason,
            'completion_request_id' => $stamp->completionRequestId,
        ]);
    }

    /**
     * @return array{level: ScoreLevel, sub_criterion_id: int|null, guiding_point_id: int|null}
     */
    private function nodeColumns(ScorableUnit $unit): array
    {
        return [
            'level' => $unit->level,
            'sub_criterion_id' => $unit->level === ScoreLevel::Sub ? $unit->id : null,
            'guiding_point_id' => $unit->level === ScoreLevel::Point ? $unit->id : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function latestVersions(Assessment $assessment): array
    {
        $versions = [];

        $rows = AssessmentScore::query()
            ->where('assessment_id', $assessment->id)
            ->selectRaw('level, coalesce(sub_criterion_id, guiding_point_id) as node_id, max(version) as latest_version')
            ->groupBy('level', 'sub_criterion_id', 'guiding_point_id')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $level = ScoreLevel::from(ScalarValue::string($row->level ?? null));
            $versions[ScorableUnit::keyFor($level, ScalarValue::int($row->node_id ?? null))] = ScalarValue::int($row->latest_version ?? null);
        }

        return $versions;
    }
}
