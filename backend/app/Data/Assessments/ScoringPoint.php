<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScoringType;
use App\Support\ValueObjects\Weight;

final readonly class ScoringPoint
{
    public function __construct(public int $id, public string $code, public Weight $weight, public ScoringType $scoringType) {}

    public function unit(): ScorableUnit
    {
        return new ScorableUnit(ScoreLevel::Point, $this->id, $this->code, $this->scoringType);
    }
}
