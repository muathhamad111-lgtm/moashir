<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\Weight;

final readonly class ScoringMain
{
    /**
     * @param  list<ScoringSub>  $subs
     */
    public function __construct(public int $id, public string $code, public Weight $weight, public array $subs) {}
}
