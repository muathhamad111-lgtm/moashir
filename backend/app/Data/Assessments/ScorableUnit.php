<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Criteria\ScoringType;

final readonly class ScorableUnit
{
    public function __construct(public ScoreLevel $level, public int $id, public string $code, public ScoringType $scoringType) {}

    /**
     * The type this unit is scored with in a request: a request stamped with a legacy scale keeps that scale for
     * every unit, an unstamped request uses the unit's own type.
     */
    public function scoringTypeUnder(?ScaleKind $stamp): ScoringType
    {
        return $stamp?->scoringType() ?? $this->scoringType;
    }

    public static function keyFor(ScoreLevel $level, int $id): string
    {
        return $level->value.':'.$id;
    }

    /**
     * The name the review form gives the unit's input (`point-31`, `sub-12`).
     */
    public static function inputNameFor(ScoreLevel $level, int $id): string
    {
        return $level->value.'-'.$id;
    }

    public function inputName(): string
    {
        return self::inputNameFor($this->level, $this->id);
    }

    public function key(): string
    {
        return self::keyFor($this->level, $this->id);
    }

    public function fieldKey(): string
    {
        return 'scores.'.($this->level === ScoreLevel::Sub ? 'subs' : 'points').'.'.$this->id;
    }

    /**
     * @return array{level: string, id: int, code: string}
     */
    public function toArray(): array
    {
        return ['level' => $this->level->value, 'id' => $this->id, 'code' => $this->code];
    }
}
