<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Identity\RoleAssignmentRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Notifications\SecurityAlerter;
use App\Support\Audit\ActorRoles;

final readonly class RoleAssignmentGuard
{
    private const array FORBIDDEN_COMBINATIONS = [
        [RoleKey::SystemAdmin, RoleKey::FinanceOfficer],
    ];

    public function __construct(private AuditRecorder $audit, private SecurityAlerter $alerter) {}

    /**
     * @param  list<string>  $requestedRoles
     * @return list<RoleKey>
     */
    public function approve(User $actor, ?User $target, array $requestedRoles): array
    {
        $this->rejectSuperAdminMarker($target ?? $actor, $requestedRoles);

        $roles = $this->assignableRoles($requestedRoles);

        if ($target instanceof User && $actor->is($target)) {
            throw RoleAssignmentRejected::selfGrant();
        }

        $this->assertNoForbiddenCombination($target, $roles);

        return $roles;
    }

    /**
     * @param  list<string>  $requestedRoles
     */
    private function rejectSuperAdminMarker(User $subject, array $requestedRoles): void
    {
        if (! in_array(ActorRoles::SUPER_ADMIN_MARKER, $requestedRoles, true)) {
            return;
        }

        $this->audit->record(new AuditEntry(AuditEvent::RoleAssignmentRejected, $subject, [], ['requested' => ActorRoles::SUPER_ADMIN_MARKER], ['security']));
        $this->alerter->raise(NotificationCode::SuperAdminAssignmentRejected);

        throw RoleAssignmentRejected::notAssignable();
    }

    /**
     * @param  list<string>  $requestedRoles
     * @return list<RoleKey>
     */
    private function assignableRoles(array $requestedRoles): array
    {
        $roles = [];

        foreach (array_unique($requestedRoles) as $value) {
            $role = RoleKey::tryFrom($value);

            if ($role === null || ! $role->isAssignable()) {
                throw RoleAssignmentRejected::notAssignable();
            }

            $roles[] = $role;
        }

        return $roles;
    }

    /**
     * @param  list<RoleKey>  $roles
     */
    private function assertNoForbiddenCombination(?User $target, array $roles): void
    {
        if ($target?->isEntityUser() === true && $roles !== []) {
            throw RoleAssignmentRejected::forbiddenCombination();
        }

        foreach (self::FORBIDDEN_COMBINATIONS as $combination) {
            $held = array_filter($combination, static fn (RoleKey $role): bool => in_array($role, $roles, true));

            if (count($held) === count($combination)) {
                throw RoleAssignmentRejected::forbiddenCombination();
            }
        }
    }
}
