<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Criteria\ScoringType;
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

    public function main(int $id): ?ScoringMain
    {
        return array_find($this->mains, static fn (ScoringMain $main): bool => $main->id === $id);
    }

    public function unit(ScoreLevel $level, int $id): ?ScorableUnit
    {
        return $this->unitsByKey[ScorableUnit::keyFor($level, $id)] ?? null;
    }

    /**
     * The type a stored score row is read with. A row whose node left the tree falls back to the request's stamp,
     * then to the relative range, which every stored raw value fits.
     */
    public function scoringTypeOf(ScoreLevel $level, int $id, ?ScaleKind $stamp): ScoringType
    {
        return $this->unit($level, $id)?->scoringTypeUnder($stamp) ?? $stamp?->scoringType() ?? ScoringType::Relative;
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
