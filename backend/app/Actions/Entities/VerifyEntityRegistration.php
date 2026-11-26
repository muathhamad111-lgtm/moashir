<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Events\Entities\EntityApproved;
use App\Events\Entities\EntityVerified;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Settings\EntityRegistrationReview;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class VerifyEntityRegistration
{
    private const string AUTOMATIC_APPROVAL = 'automatic';

    public function __construct(private AuditRecorder $audit, private EntityRegistrationReview $review) {}

    public function handle(User $owner): void
    {
        if ($owner->entity_id === null) {
            return;
        }

        DB::transaction(function () use ($owner): void {
            $entity = Entity::query()->whereKey($owner->entity_id)->lockForUpdate()->first();

            if (! $entity instanceof Entity) {
                return;
            }

            $owner->forceFill(['email_verified_at' => $owner->email_verified_at ?? Date::now()])->save();

            if (! $entity->isPendingVerification()) {
                $this->recordConfirmedEmail($entity);

                return;
            }

            if ($this->review->isRequired()) {
                $this->holdForReview($entity);

                return;
            }

            $this->activateAutomatically($entity);
        });
    }

    private function holdForReview(Entity $entity): void
    {
        $entity->forceFill(['status' => EntityStatus::PendingReview, 'verified_at' => Date::now()])->save();

        $this->recordVerified($entity, ['status' => EntityStatus::PendingReview->value]);

        EntityVerified::dispatch($entity);
    }

    private function activateAutomatically(Entity $entity): void
    {
        $now = Date::now();
        $entity->forceFill(['status' => EntityStatus::Active, 'verified_at' => $now, 'approved_at' => $now])->save();

        $this->recordVerified($entity, ['status' => EntityStatus::Active->value, 'approval' => self::AUTOMATIC_APPROVAL]);

        EntityVerified::dispatch($entity);
        EntityApproved::dispatch($entity);
    }

    /**
     * @param  array<string, string>  $newValues
     */
    private function recordVerified(Entity $entity, array $newValues): void
    {
        $this->audit->record(new AuditEntry(
            AuditEvent::EntityVerified,
            $entity,
            ['status' => EntityStatus::PendingVerification->value],
            $newValues,
        ));
    }

    /**
     * Staff may approve an entity before its owner confirmed the e-mail; the confirmation then
     * still marks the entity verified, so it joins the periodic-assessment audience.
     */
    private function recordConfirmedEmail(Entity $entity): void
    {
        if ($entity->verified_at !== null) {
            return;
        }

        $entity->forceFill(['verified_at' => Date::now()])->save();
    }
}
