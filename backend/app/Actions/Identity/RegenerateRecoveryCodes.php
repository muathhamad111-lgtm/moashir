<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Exceptions\Identity\MfaRejected;
use App\Models\Identity\User;
use App\Support\StringList;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

final readonly class RegenerateRecoveryCodes
{
    public function __construct(private GenerateNewRecoveryCodes $generate) {}

    /**
     * @return list<string>
     */
    public function handle(User $user): array
    {
        if (! $user->hasConfirmedTotp()) {
            throw MfaRejected::notEnrolled();
        }

        ($this->generate)($user);

        return StringList::from($user->fresh()?->recoveryCodes());
    }
}
