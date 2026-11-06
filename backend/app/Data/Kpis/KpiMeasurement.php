<?php

declare(strict_types=1);

namespace App\Data\Kpis;

use App\Support\ValueObjects\KpiNumber;
use Carbon\CarbonImmutable;

final readonly class KpiMeasurement
{
    public function __construct(public KpiNumber $value, public CarbonImmutable $measuredOn) {}
}
