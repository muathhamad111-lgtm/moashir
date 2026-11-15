<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Events\Entities\EntityApproved;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class EntityApproval
{
    private const string VERB = 'approve';

    public function __construct(private AuditRecorder $audit) {}

    public function approve(Entity $entity): void
    {
        DB::transaction(function () use ($entity): void {
            $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingVerification() && ! $locked->isPendingReview() && ! $locked->isRejected()) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $before = $locked->status->value;
            $entity->forceFill([
                'status' => EntityStatus::Active,
                'approved_at' => Date::now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            $this->audit->record(new AuditEntry(AuditEvent::EntityApproved, $entity, ['status' => $before], ['status' => EntityStatus::Active->value]));

            EntityApproved::dispatch($entity);
        });
    }
}
