<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;

final class ListSuperAdmins
{
    /**
     * @return list<array{email: string, mfa_enrolled: bool, last_login_at: string|null}>
     */
    public function handle(): array
    {
        return array_values(User::query()
            ->superAdmins()
            ->orderBy('id')
            ->get()
            ->map(static fn (User $user): array => [
                'email' => new EmailAddress($user->email)->masked(),
                'mfa_enrolled' => $user->hasConfirmedTotp(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ])
            ->all());
    }
}
