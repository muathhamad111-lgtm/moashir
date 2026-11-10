<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Support\ValueObjects\PeriodWindow;

final readonly class PlannedPeriod
{
    public function __construct(public PeriodWindow $window, public ?int $id) {}
}
