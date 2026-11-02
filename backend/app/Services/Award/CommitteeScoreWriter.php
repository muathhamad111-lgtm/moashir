<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoreChange;
use App\Data\Assessments\ScoreChangeSet;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScaleKind;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeScore;
use App\Models\Identity\User;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use App\Support\ValueObjects\RawScore;
use Illuminate\Support\Facades\Date;

final readonly class CommitteeScoreWriter
{
    public function __construct(private CommitteeScoreReader $reader, private RequestContext $context) {}

    public function replace(CommitteeAssignment $assignment, ScoringTree $tree, ScoreSheet $desired, User $scorer, ScaleKind $scale): ScoreChangeSet
    {
        $changes = [];
        $current = [];

        foreach ($this->reader->currentRows($assignment) as $row) {
            $key = ScorableUnit::keyFor($row->level, $row->nodeId());
            $current[$key] = $row;
            $wanted = $desired->rawForKey($key);

            if ($wanted instanceof RawScore && $wanted->equals($row->rawScore($scale))) {
                continue;
            }

            $row->forceFill(['superseded_at' => Date::now()])->save();

            if (! $wanted instanceof RawScore) {
                $changes[] = new ScoreChange($tree->unit($row->level, $row->nodeId())->code ?? $key, $row->rawScore($scale), null);
            }
        }

        $versions = $this->latestVersions($assignment);

        foreach ($desired->entries() as $scored) {
            $before = ($current[$scored->unit->key()] ?? null)?->rawScore($scale);

            if ($before instanceof RawScore && $before->equals($scored->raw)) {
                continue;
            }

            CommitteeScore::query()->create([
                'committee_assignment_id' => $assignment->id,
                'level' => $scored->unit->level,
                'sub_criterion_id' => $scored->unit->level === ScoreLevel::Sub ? $scored->unit->id : null,
                'guiding_point_id' => $scored->unit->level === ScoreLevel::Point ? $scored->unit->id : null,
                'scorer_user_id' => $scorer->id,
                'raw_value' => $scored->raw->toDecimal(),
                'percent' => $scored->raw->percent(),
                'version' => ($versions[$scored->unit->key()] ?? 0) + 1,
                'change_reason' => $this->context->reason(),
            ]);
            $changes[] = new ScoreChange($scored->unit->code, $before, $scored->raw);
        }

        return new ScoreChangeSet($changes);
    }

    /**
     * @return array<string, int>
     */
    private function latestVersions(CommitteeAssignment $assignment): array
    {
        $versions = [];

        $rows = CommitteeScore::query()
            ->where('committee_assignment_id', $assignment->id)
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
