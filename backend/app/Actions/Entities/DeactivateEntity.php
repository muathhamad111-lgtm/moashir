<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Events\Entities\EntityDeactivated;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Identity\UserSessionTerminator;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateEntity
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private RequestContext $context,
        private UserSessionTerminator $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Entity $entity, string $reason): Entity
    {
        $this->freezeGuard->assertNotFrozen($entity);
        $this->context->recordReason($reason);

        DB::transaction(function () use ($actor, $entity, $reason): void {
            $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw IllegalStateTransition::from($locked->status->value, 'deactivate');
            }

            $entity->forceFill([
                'status' => EntityStatus::Deactivated,
                'deactivated_at' => Date::now(),
                'deactivation_reason' => $reason,
            ])->save();

            $owner = $entity->owner()->first();

            if ($owner instanceof User) {
                $this->sessions->terminateAll($owner);
            }

            $this->audit->record(new AuditEntry(
                AuditEvent::EntityDeactivated,
                $entity,
                ['status' => EntityStatus::Active->value],
                ['status' => EntityStatus::Deactivated->value],
            ));

            EntityDeactivated::dispatch($entity, $actor->id);
        });

        return $entity;
    }
}
