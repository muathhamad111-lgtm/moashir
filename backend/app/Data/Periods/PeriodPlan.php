<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Support\ValueObjects\PeriodWindow;

final readonly class PeriodPlan
{
    /**
     * @param  list<PeriodWindow>  $started
     * @param  list<PeriodWindow>  $upcoming
     */
    public function __construct(public array $started, public array $upcoming) {}

    public static function empty(): self
    {
        return new self([], []);
    }
}
