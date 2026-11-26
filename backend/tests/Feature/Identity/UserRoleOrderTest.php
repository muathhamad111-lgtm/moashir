<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;

it('lists a user roles in catalog order whatever the order they were granted in', function (): void {
    $user = unqualifiedStaffUser(RoleKey::FilteringOfficer, RoleKey::SystemAdmin, RoleKey::Assessor);

    expect($user->refresh()->roleKeys())->toBe([RoleKey::SystemAdmin, RoleKey::Assessor, RoleKey::FilteringOfficer]);

    signIn(systemAdmin());
    $this->getJson("/api/v1/users/{$user->id}")->assertOk()->assertJsonPath('data.roles', ['system_admin', 'assessor', 'filtering_officer']);
});

it('keeps only known roles, each once, in catalog order', function (): void {
    expect(RoleKey::inCatalogOrder([RoleKey::Entity, RoleKey::Assessor, RoleKey::Assessor, RoleKey::SystemAdmin]))
        ->toBe([RoleKey::SystemAdmin, RoleKey::Assessor, RoleKey::Entity])
        ->and(RoleKey::inCatalogOrder([]))->toBe([]);
});
