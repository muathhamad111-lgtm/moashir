<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Support\FixedPoint;
use App\Support\ValueObjects\Percent;
use InvalidArgumentException;

final class CommitteeAverageCalculator
{
    /**
     * @param  list<Percent>  $percents
     */
    public function average(array $percents): Percent
    {
        if ($percents === []) {
            throw new InvalidArgumentException('A committee average needs at least one submitted evaluation.');
        }

        $sum = array_sum(array_map(static fn (Percent $percent): int => $percent->tenThousandths, $percents));

        return Percent::fromTenThousandths(FixedPoint::divideRoundingHalfUp($sum, count($percents)));
    }
}
