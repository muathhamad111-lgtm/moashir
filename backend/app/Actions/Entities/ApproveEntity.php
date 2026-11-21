<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Entities\EntityApproval;
use App\Services\Entities\EntityFreezeGuard;

final readonly class ApproveEntity
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private StaffActionReason $reasons,
        private EntityApproval $approval,
    ) {}

    public function handle(User $actor, Entity $entity, ?string $reason): Entity
    {
        $this->freezeGuard->assertNotFrozen($entity);
        $this->reasons->record($actor, $reason);
        $this->approval->approve($entity);

        return $entity;
    }
}
