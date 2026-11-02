<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Identity\SessionGroup;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;

it('reports a locked active account as locked until the lockout elapses', function (): void {
    $user = User::factory()->locked()->make();

    expect($user->effectiveStatus())->toBe(UserStatus::Locked);

    $this->travel(16)->minutes();
    expect($user->effectiveStatus())->toBe(UserStatus::Active);
});

it('derives the session group from the account', function (): void {
    expect(superAdmin()->sessionGroup())->toBe(SessionGroup::SuperAdmin)
        ->and(entityUser()->sessionGroup())->toBe(SessionGroup::Entity)
        ->and(staffUser(RoleKey::Assessor)->sessionGroup())->toBe(SessionGroup::Staff);
});

it('requires MFA for the Super Admin and for staff', function (): void {
    expect(superAdmin()->requiresMfa())->toBeTrue()
        ->and(staffUser(RoleKey::ContentOfficer)->requiresMfa())->toBeTrue()
        ->and(entityUser()->requiresMfa())->toBeFalse();
});

it('hides the secrets and the Super Admin flag from serialization', function (): void {
    $serialized = superAdmin()->toArray();

    expect($serialized)->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'is_super_admin']);
});
