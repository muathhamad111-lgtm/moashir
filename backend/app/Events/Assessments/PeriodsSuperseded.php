<?php

declare(strict_types=1);

namespace App\Events\Assessments;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class PeriodsSuperseded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $periodIds
     */
    public function __construct(public array $periodIds) {}
}
