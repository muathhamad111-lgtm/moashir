<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Events\Entities\EntityRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Identity\UserSessionTerminator;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RejectEntity
{
    private const string VERB = 'reject';

    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private RequestContext $context,
        private UserSessionTerminator $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(Entity $entity, string $reason): Entity
    {
        $this->freezeGuard->assertNotFrozen($entity);
        $this->context->recordReason($reason);

        DB::transaction(function () use ($entity, $reason): void {
            $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingVerification() && ! $locked->isPendingReview()) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $before = $locked->status->value;
            $entity->forceFill([
                'status' => EntityStatus::Rejected,
                'rejected_at' => Date::now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->endOwnerSessions($entity);

            $this->audit->record(new AuditEntry(AuditEvent::EntityRejected, $entity, ['status' => $before], ['status' => EntityStatus::Rejected->value]));

            EntityRejected::dispatch($entity, $reason);
        });

        return $entity;
    }

    private function endOwnerSessions(Entity $entity): void
    {
        $owner = $entity->owner()->first();

        if ($owner instanceof User) {
            $this->sessions->terminateAll($owner);
        }
    }
}
