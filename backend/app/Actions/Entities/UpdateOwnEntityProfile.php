<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntityProfileUpdated;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityProfileWriter;
use App\Services\Entities\MarketingConsentRecorder;
use App\Services\Entities\OwnEntity;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOwnEntityProfile
{
    public function __construct(
        private OwnEntity $ownEntity,
        private EntityProfileWriter $writer,
        private MarketingConsentRecorder $consent,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $owner, EntityProfileData $profile, EntityContactData $contact, bool $marketingConsent): Entity
    {
        $entity = $this->ownEntity->of($owner);

        if (! $entity->isActive()) {
            throw IllegalStateTransition::from($entity->status->value, 'update_profile');
        }

        DB::transaction(function () use ($owner, $entity, $profile, $contact, $marketingConsent): void {
            $changes = $this->writer->write($entity, $profile, $contact, $owner);
            $this->consent->record($entity, $marketingConsent);

            $this->audit->record(new AuditEntry(AuditEvent::EntityProfileUpdated, $entity, $changes->before, [
                ...$changes->after,
                'marketing_consent' => $marketingConsent,
            ]));

            if (! $changes->isEmpty()) {
                EntityProfileUpdated::dispatch($entity, $changes->fields(), $owner->id);
            }
        });

        return $entity->refresh();
    }
}
