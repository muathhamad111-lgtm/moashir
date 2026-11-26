<?php

declare(strict_types=1);

namespace App\Policies\Award;

use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\CycleRecordScope;
use Illuminate\Auth\Access\Response;

/**
 * The cycle-wide filtering decision «تأهيل كل من بلغ حد التأهل»: the same deciders as a single request (the
 * cycle's filtering officers, the Super Admin); readers of the filtering list get 403, anyone else 404.
 */
final readonly class BulkFilteringPolicy
{
    public function __construct(private CycleRecordScope $scope, private AwardCyclePolicy $cycles) {}

    public function qualifyEligible(User $actor, AwardCycle $cycle): Response
    {
        if ($this->scope->canDecideFiltering($actor, $cycle)) {
            return Response::allow();
        }

        $reads = $this->cycles->viewFiltering($actor, $cycle);

        return $reads->allowed() ? Response::deny() : $reads;
    }
}
