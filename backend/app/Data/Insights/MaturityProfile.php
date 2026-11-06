<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\MaturityBand;
use App\Support\ValueObjects\MaturityBounds;

final readonly class MaturityProfile
{
    /**
     * @param  list<BandedMain>  $mains
     */
    public function __construct(public MaturityBounds $rules, public MaturityBand $overall, public array $mains) {}

    /**
     * @return array<string, int>
     */
    public function mainCounts(): array
    {
        $counts = array_fill_keys(MaturityBand::values(), 0);

        foreach ($this->mains as $main) {
            $counts[$main->band->value]++;
        }

        return $counts;
    }
}
