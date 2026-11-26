<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\RawScore;

/**
 * The score sheet a save asks for, with the units the request named explicitly: an explicit unit is re-recorded in
 * a new completion round even when its value is unchanged, so the assessor can confirm it as the round's result.
 */
final readonly class DesiredScores
{
    public function __construct(public ScoreSheet $sheet, public ScoreSheet $given) {}

    public function reconfirms(string $key, ?int $storedRound, ?int $currentRound): bool
    {
        return $currentRound !== null && $storedRound !== $currentRound && $this->given->rawForKey($key) instanceof RawScore;
    }
}
