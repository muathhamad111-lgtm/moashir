<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\CycleCommitteeAssessor;

final readonly class RosterChangeOutcome
{
    /**
     * @param  list<int>  $affectedRequestIds
     * @param  list<int>  $recomputedRequestIds
     */
    public function __construct(public CycleCommitteeAssessor $member, public array $affectedRequestIds, public array $recomputedRequestIds) {}
}
