<?php

declare(strict_types=1);

namespace App\Support\Periods;

use App\Enums\Periods\PeriodCadence;
use App\Support\ValueObjects\LocalizedText;

final class PeriodLabel
{
    public static function of(PeriodCadence $cadence, int $year, int $sequence): LocalizedText
    {
        return LocalizedText::fromTranslationWith(
            'assessments.periods.labels.'.$cadence->value.'.'.$sequence,
            ['year' => (string) $year],
        );
    }

    public static function keyOf(PeriodCadence $cadence, int $year, int $sequence): string
    {
        return match ($cadence) {
            PeriodCadence::Quarterly => sprintf('%d-Q%d', $year, $sequence),
            PeriodCadence::SemiAnnual => sprintf('%d-H%d', $year, $sequence),
            PeriodCadence::Annual => (string) $year,
        };
    }
}
