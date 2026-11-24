<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\QualificationCandidates;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\QualificationCandidateFinder;

/**
 * The preview the bulk qualification asks the officer to confirm: who would be qualified, and how many of the
 * qualifying requests are left out because the officer assessed them.
 */
final readonly class ListQualificationCandidates
{
    public function __construct(private QualificationCandidateFinder $candidates) {}

    public function handle(User $actor, AwardCycle $cycle): QualificationCandidates
    {
        return $this->candidates->of($cycle, $actor);
    }
}
