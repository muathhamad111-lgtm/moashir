<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\MaturityBand;

final readonly class BandedMain
{
    /**
     * @param  list<BandedSub>  $subs
     */
    public function __construct(public ResultMain $main, public MaturityBand $band, public array $subs) {}
}
