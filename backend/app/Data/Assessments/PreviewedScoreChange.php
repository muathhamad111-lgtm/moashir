<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\RawScore;

final readonly class PreviewedScoreChange
{
    public function __construct(public ScorableUnit $unit, public ?RawScore $before, public ?RawScore $after) {}

    public static function between(ScorableUnit $unit, ?RawScore $before, ?RawScore $after): ?self
    {
        $unchanged = $before instanceof RawScore && $after instanceof RawScore ? $before->equals($after) : $before === $after;

        return $unchanged ? null : new self($unit, $before, $after);
    }
}
