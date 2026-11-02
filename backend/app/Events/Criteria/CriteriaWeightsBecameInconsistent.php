<?php

declare(strict_types=1);

namespace App\Events\Criteria;

use App\Data\Criteria\ConsistencyReport;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CriteriaWeightsBecameInconsistent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CriteriaVersion $version, public ConsistencyReport $report, public ?int $actorId) {}
}
