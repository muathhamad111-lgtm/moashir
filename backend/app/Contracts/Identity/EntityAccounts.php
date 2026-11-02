<?php

declare(strict_types=1);

namespace App\Contracts\Identity;

use App\Data\Identity\EntitySessionSummary;
use App\Enums\Identity\EntityStanding;
use App\Models\Identity\User;

interface EntityAccounts
{
    public function standingOf(User $user): EntityStanding;

    public function confirmVerification(User $user): void;

    public function sessionSummaryOf(User $user): ?EntitySessionSummary;
}
