<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\EvaluationType;
use App\Support\ValueObjects\Weight;

final readonly class ScoringSub
{
    /**
     * @param  list<ScoringPoint>  $points
     */
    public function __construct(
        public int $id,
        public string $code,
        public Weight $weight,
        public EvaluationType $evaluationType,
        public array $points = [],
    ) {}

    public function scoresPoints(): bool
    {
        return $this->evaluationType->scoresGuidingPoints();
    }

    public function unit(): ScorableUnit
    {
        return new ScorableUnit(ScoreLevel::Sub, $this->id, $this->code);
    }

    /**
     * @return list<ScorableUnit>
     */
    public function scorableUnits(): array
    {
        if (! $this->scoresPoints()) {
            return [$this->unit()];
        }

        return array_map(static fn (ScoringPoint $point): ScorableUnit => $point->unit(), $this->points);
    }
}
