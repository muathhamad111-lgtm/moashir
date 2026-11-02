<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\RawScore;

final readonly class ScoredUnit
{
    public function __construct(public ScorableUnit $unit, public RawScore $raw) {}
}
