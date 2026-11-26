<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum ReviewNoteLevel: string
{
    case Main = 'main';
    case Sub = 'sub';
    case Point = 'point';

    public function rank(): int
    {
        return match ($this) {
            self::Main => 1,
            self::Sub => 2,
            self::Point => 3,
        };
    }

    public function hasSub(): bool
    {
        return $this !== self::Main;
    }

    public function hasPoint(): bool
    {
        return $this === self::Point;
    }
}
