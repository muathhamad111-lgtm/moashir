<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\RawScore;

final readonly class ScoreChange
{
    public function __construct(public string $code, public ?RawScore $before, public ?RawScore $after) {}
}
