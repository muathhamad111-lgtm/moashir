<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Entities\Entity;
use App\Services\Entities\EntityFreezeGuard;

final readonly class AssessmentEntityGuard
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    public function assertMayAct(Entity $entity): void
    {
        $this->freezeGuard->assertNotFrozen($entity);

        if (! $entity->isActive()) {
            throw AssessmentRejected::entityNotActive();
        }
    }
}
