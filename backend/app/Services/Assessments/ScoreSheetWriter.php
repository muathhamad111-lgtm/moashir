<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CarriedScore;
use App\Data\Assessments\RebindPlan;
use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoreChange;
use App\Data\Assessments\ScoreChangeSet;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Assessments\ScorerRole;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentScore;
use App\Models\Identity\User;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use App\Support\ValueObjects\RawScore;
use Illuminate\Support\Facades\Date;

final readonly class ScoreSheetWriter
{
    public function __construct(private ScoreSheetReader $reader, private RequestContext $context) {}

    public function replace(Assessment $assessment, ScoringTree $tree, ScoreSheet $desired, User $scorer, ScorerRole $role): ScoreChangeSet
    {
        $current = $this->currentByKey($assessment);
        $superseded = [];
        $cleared = [];

        foreach ($current as $key => $row) {
            $wanted = $desired->rawForKey($key);

            if ($wanted instanceof RawScore && $wanted->equals($row->rawScore($assessment->scale_kind))) {
                continue;
            }

            $superseded[] = $row;

            if (! $wanted instanceof RawScore) {
                $cleared[] = new ScoreChange($tree->unit($row->level, $row->nodeId())->code ?? $key, $row->rawScore($assessment->scale_kind), null);
            }
        }

        $this->supersede($superseded);

        return new ScoreChangeSet([...$cleared, ...$this->insertChanged($assessment, $current, $desired, $scorer, $role)]);
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
    private function insertChanged(Assessment $assessment, array $current, ScoreSheet $desired, User $scorer, ScorerRole $role): array
    {
        $versions = $this->latestVersions($assessment);
        $changes = [];

        foreach ($desired->entries() as $scored) {
            $before = ($current[$scored->unit->key()] ?? null)?->rawScore($assessment->scale_kind);

            if ($before instanceof RawScore && $before->equals($scored->raw)) {
                continue;
            }

            $this->insert($assessment, $scored, $scorer, $role, ($versions[$scored->unit->key()] ?? 0) + 1);
            $changes[] = new ScoreChange($scored->unit->code, $before, $scored->raw);
        }

        return $changes;
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

    private function insert(Assessment $assessment, ScoredUnit $scored, User $scorer, ScorerRole $role, int $version): void
    {
        AssessmentScore::query()->create([
            'assessment_id' => $assessment->id,
            ...$this->nodeColumns($scored->unit),
            'scorer_user_id' => $scorer->id,
            'scorer_role' => $role,
            'raw_value' => $scored->raw->toDecimal(),
            'percent' => $scored->raw->percent(),
            'version' => $version,
            'change_reason' => $this->context->reason(),
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
