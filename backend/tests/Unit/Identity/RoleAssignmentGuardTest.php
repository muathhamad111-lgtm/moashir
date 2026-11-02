<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Identity\RoleAssignmentRejected;
use App\Models\Audit\Audit;
use App\Services\Identity\RoleAssignmentGuard;

function guard(): RoleAssignmentGuard
{
    return app(RoleAssignmentGuard::class);
}

it('approves assignable roles and removes duplicates', function (): void {
    expect(guard()->approve(systemAdmin(), null, ['assessor', 'content_officer', 'assessor']))
        ->toBe([RoleKey::Assessor, RoleKey::ContentOfficer]);
});

it('rejects unknown and non-assignable roles', function (array $roles): void {
    expect(fn (): array => guard()->approve(systemAdmin(), null, $roles))->toThrow(RoleAssignmentRejected::class);
})->with([[['entity']], [['judge']], [['admin']]]);

it('records a security event when the Super Admin marker is requested', function (): void {
    expect(fn (): array => guard()->approve(systemAdmin(), null, ['super_admin']))->toThrow(RoleAssignmentRejected::class)
        ->and(Audit::query()->where('event', AuditEvent::RoleAssignmentRejected->value)->count())->toBe(1);
});

it('rejects the finance officer with the system administrator (SoD-5)', function (): void {
    expect(fn (): array => guard()->approve(systemAdmin(), null, ['finance_officer', 'system_admin']))->toThrow(RoleAssignmentRejected::class);
});

it('allows the follower with the filtering officer and the system administrator with the award secretary', function (): void {
    expect(guard()->approve(systemAdmin(), null, ['request_follower', 'filtering_officer']))->toHaveCount(2)
        ->and(guard()->approve(systemAdmin(), null, ['system_admin', 'award_secretary']))->toHaveCount(2);
});

it('rejects self assignment (SoD-7) and staff roles for entity accounts (SoD-8)', function (): void {
    $administrator = systemAdmin();

    expect(fn (): array => guard()->approve($administrator, $administrator, ['system_admin']))->toThrow(RoleAssignmentRejected::class)
        ->and(fn (): array => guard()->approve($administrator, entityUser(), ['assessor']))->toThrow(RoleAssignmentRejected::class);
});
