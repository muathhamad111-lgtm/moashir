<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Events\Entities\EntityVerified;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class VerifyEntityRegistration
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $owner): void
    {
        if ($owner->entity_id === null) {
            return;
        }

        DB::transaction(function () use ($owner): void {
            $entity = Entity::query()->whereKey($owner->entity_id)->lockForUpdate()->first();

            if (! $entity instanceof Entity || ! $entity->isPendingVerification()) {
                return;
            }

            $entity->forceFill(['status' => EntityStatus::Active, 'verified_at' => Date::now()])->save();
            $owner->forceFill(['email_verified_at' => $owner->email_verified_at ?? Date::now()])->save();

            $this->audit->record(new AuditEntry(
                AuditEvent::EntityVerified,
                $entity,
                ['status' => EntityStatus::PendingVerification->value],
                ['status' => EntityStatus::Active->value],
            ));

            EntityVerified::dispatch($entity);
        });
    }
}
