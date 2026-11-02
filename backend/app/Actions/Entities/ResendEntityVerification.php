<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntityVerificationResent;
use App\Exceptions\Entities\EntityRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Entities\EntitySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ResendEntityVerification
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private EntitySettings $settings,
        private AuditRecorder $audit,
    ) {}

    public function handle(Entity $entity): Entity
    {
        $this->freezeGuard->assertNotFrozen($entity);

        DB::transaction(function () use ($entity): void {
            $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingVerification()) {
                throw IllegalStateTransition::from($locked->status->value, 'resend_verification');
            }

            $this->assertCooldownElapsed($locked->verification_reminded_at);

            $entity->forceFill(['verification_reminded_at' => Date::now()])->save();
            $this->audit->record(new AuditEntry(AuditEvent::EntityVerificationResent, $entity));

            EntityVerificationResent::dispatch($entity);
        });

        return $entity;
    }

    private function assertCooldownElapsed(?Carbon $remindedAt): void
    {
        if (! $remindedAt instanceof Carbon) {
            return;
        }

        $availableAt = $remindedAt->copy()->addMinutes($this->settings->verificationReminderCooldownMinutes());

        if ($availableAt->isFuture()) {
            throw EntityRejected::verificationCooldown((int) ceil(Date::now()->diffInSeconds($availableAt, true)));
        }
    }
}
