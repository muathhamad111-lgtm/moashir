<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\MaturityBand;

final readonly class BandedSub
{
    public function __construct(public ResultSub $sub, public MaturityBand $band) {}
}
