<?php

declare(strict_types=1);

namespace App\Events\Kpis;

use App\Models\Kpis\Kpi;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class KpiUnpublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Kpi $kpi, public ?int $actorId) {}
}
