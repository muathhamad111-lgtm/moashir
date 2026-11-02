<?php

declare(strict_types=1);

use App\Models\Identity\User;
use App\Services\Identity\PasswordPolicy;
use Illuminate\Support\Facades\Validator;

function passwordPasses(string $password, ?User $user = null): bool
{
    return Validator::make(
        ['password' => $password, 'password_confirmation' => $password],
        ['password' => app(PasswordPolicy::class)->rules($user)],
    )->passes();
}

it('requires at least twelve characters', function (): void {
    expect(passwordPasses('Short-2026!'))->toBeFalse()
        ->and(passwordPasses('Long-Enough-26'))->toBeTrue();
});

it('rejects passwords known to the breach checker', function (): void {
    markPasswordBreached('Compromised-Password-1');

    expect(passwordPasses('Compromised-Password-1'))->toBeFalse();
});

it('rejects the current password of the user', function (): void {
    expect(passwordPasses(defaultPassword(), entityUser()))->toBeFalse()
        ->and(passwordPasses('A-New-Password-2026', entityUser()))->toBeTrue();
});
