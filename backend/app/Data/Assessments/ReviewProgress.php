<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class ReviewProgress
{
    public function __construct(public int $scored, public int $required) {}

    /**
     * Counts the scorable units of the tree that carry a current score.
     *
     * @param  array<string, true>  $scoredKeys  unit keys ({@see ScorableUnit::key()}) with a current score
     */
    public static function of(ScoringTree $tree, array $scoredKeys): self
    {
        $units = $tree->scorableUnits();
        $scored = 0;

        foreach ($units as $unit) {
            $scored += isset($scoredKeys[$unit->key()]) ? 1 : 0;
        }

        return new self($scored, count($units));
    }

    /**
     * @return array{scored: int, required: int}
     */
    public function toArray(): array
    {
        return ['scored' => $this->scored, 'required' => $this->required];
    }
}
