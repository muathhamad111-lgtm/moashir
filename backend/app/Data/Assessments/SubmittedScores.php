<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class SubmittedScores
{
    /**
     * @param  array<array-key, mixed>  $subs
     * @param  array<array-key, mixed>  $points
     */
    public function __construct(public array $subs = [], public array $points = []) {}
}
