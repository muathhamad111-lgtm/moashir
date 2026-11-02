<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Identity\ApplyNewPassword;
use App\Data\Demo\DemoAccount;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use App\Services\Identity\UserSessionTerminator;

final readonly class DemoPasswordRotator
{
    public function __construct(
        private DemoPasswordGenerator $passwords,
        private ApplyNewPassword $applyPassword,
        private UserSessionTerminator $sessions,
    ) {}

    public function rotate(User $user): DemoAccount
    {
        $password = $this->passwords->generate();
        $this->applyPassword->handle($user, $password->reveal());
        $this->sessions->terminateAll($user);
        $role = $user->roles->first()->name ?? RoleKey::Entity->value;

        return new DemoAccount($role, $user->name, $user->email, $password, $user->mfa_required);
    }
}
