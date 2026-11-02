<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ScoreLevel;
use App\Support\ValueObjects\Weight;

final readonly class ScoringTree
{
    /**
     * @var array<string, ScorableUnit>
     */
    private array $unitsByKey;

    /**
     * @var array<string, ScorableUnit>
     */
    private array $unitsByCode;

    /**
     * @param  list<ScoringMain>  $mains
     */
    public function __construct(public int $versionId, public array $mains)
    {
        $byKey = [];
        $byCode = [];

        foreach ($this->scorableUnits() as $unit) {
            $byKey[$unit->key()] = $unit;
            $byCode[$unit->code] = $unit;
        }

        $this->unitsByKey = $byKey;
        $this->unitsByCode = $byCode;
    }

    /**
     * @return list<ScorableUnit>
     */
    public function scorableUnits(): array
    {
        $units = [];

        foreach ($this->mains as $main) {
            foreach ($main->subs as $sub) {
                array_push($units, ...$sub->scorableUnits());
            }
        }

        return $units;
    }

    public function unit(ScoreLevel $level, int $id): ?ScorableUnit
    {
        return $this->unitsByKey[ScorableUnit::keyFor($level, $id)] ?? null;
    }

    public function scoresSubThroughPoints(int $subId): bool
    {
        foreach ($this->mains as $main) {
            foreach ($main->subs as $sub) {
                if ($sub->id === $subId) {
                    return $sub->scoresPoints();
                }
            }
        }

        return false;
    }

    public function unitWithCode(string $code): ?ScorableUnit
    {
        return $this->unitsByCode[$code] ?? null;
    }

    /**
     * @return list<ScorableUnit>
     */
    public function unscoredUnits(ScoreSheet $sheet): array
    {
        return array_values(array_filter(
            $this->scorableUnits(),
            static fn (ScorableUnit $unit): bool => ! $sheet->has($unit),
        ));
    }

    public function totalWeight(): Weight
    {
        return Weight::sum(array_map(static fn (ScoringMain $main): Weight => $main->weight, $this->mains));
    }
}
