<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Support\ValueObjects\Weight;

final class EqualWeightDistributor
{
    /**
     * @return list<Weight>
     */
    public function shares(Weight $total, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $base = intdiv($total->hundredths, $count);
        $remainder = $total->hundredths % $count;

        if ($base <= 0) {
            throw CriteriaWeightRejected::tooSmallToDistribute($total, $count);
        }

        $shares = [];

        for ($index = 0; $index < $count; $index++) {
            $shares[] = Weight::fromHundredths($base + ($index < $remainder ? 1 : 0));
        }

        return $shares;
    }
}
