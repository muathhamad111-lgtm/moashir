<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Award\AwardRegionApproval;

final readonly class AwardResultsBoard
{
    /**
     * @param  list<AwardRanking>  $rankings
     * @param  array<int, AwardRegionApproval>  $approvals
     * @param  array<int, int>  $pendingByRegion
     */
    public function __construct(public AwardCycle $cycle, public array $rankings, public array $approvals, public array $pendingByRegion) {}
}
