<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Events\Entities\EntityReactivated;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class ReactivateEntity
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Entity $entity, string $reason): Entity
    {
        $this->freezeGuard->assertNotFrozen($entity);
        $this->context->recordReason($reason);

        DB::transaction(function () use ($actor, $entity): void {
            $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDeactivated()) {
                throw IllegalStateTransition::from($locked->status->value, 'reactivate');
            }

            $entity->forceFill([
                'status' => EntityStatus::Active,
                'deactivated_at' => null,
                'deactivation_reason' => null,
            ])->save();

            $this->audit->record(new AuditEntry(
                AuditEvent::EntityReactivated,
                $entity,
                ['status' => EntityStatus::Deactivated->value],
                ['status' => EntityStatus::Active->value],
            ));

            EntityReactivated::dispatch($entity, $actor->id);
        });

        return $entity;
    }
}
