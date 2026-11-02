<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Models\Identity\Role;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class SessionContext
{
    /**
     * @param  Collection<int, Role>  $roles
     * @param  list<string>  $permissions
     * @param  list<CommitteeAppointment>  $committeeAppointments
     */
    public function __construct(
        public User $user,
        public Collection $roles,
        public array $permissions,
        public ?CarbonImmutable $expiresAt,
        public int $idleTimeoutMinutes,
        public ?EntitySessionSummary $entity = null,
        public array $committeeAppointments = [],
    ) {}
}
