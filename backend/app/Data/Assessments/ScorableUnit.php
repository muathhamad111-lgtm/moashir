<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ScoreLevel;

final readonly class ScorableUnit
{
    public function __construct(public ScoreLevel $level, public int $id, public string $code) {}

    public static function keyFor(ScoreLevel $level, int $id): string
    {
        return $level->value.':'.$id;
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
