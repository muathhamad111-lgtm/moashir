<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileChangeSet;
use App\Data\Entities\EntityProfileData;
use App\Data\Entities\EntityProfileWrite;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Identity\User;
use App\Support\Entities\EntityUniqueViolation;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class EntityProfileWriter
{
    public function __construct(private EntityProfileDiff $diff, private MarketingConsentRecorder $consent) {}

    public function writeProfile(Entity $entity, EntityProfileData $profile, EntityContactData $contact, ?User $actor): EntityProfileChangeSet
    {
        return $this->write($entity, new EntityProfileWrite($profile->toAttributes(), $contact), $actor);
    }

    public function write(Entity $entity, EntityProfileWrite $write, ?User $actor): EntityProfileChangeSet
    {
        $attributes = $entity->withDerivedFoundingYear($write->attributes);
        $owner = $entity->loadMissing('owner')->owner;
        $changes = $this->diff->between($entity, $attributes)
            ->merge($this->diff->ofContact($owner, $write->contact))
            ->merge($this->diff->ofConsent($entity, $write->marketingConsent));

        $entity->fill($attributes);
        $this->writeContact($owner, $write->contact);
        $entity->syncProfileCompletedAt();
        $this->save($entity);
        $this->writeConsent($entity, $write->marketingConsent);

        $this->recordHistory($entity, $changes, $actor);

        return $changes;
    }

    private function save(Entity $entity): void
    {
        try {
            $entity->save();
        } catch (UniqueConstraintViolationException $violation) {
            throw EntityUniqueViolation::toValidation($violation) ?? $violation;
        }
    }

    private function writeContact(?User $owner, ?EntityContactData $contact): void
    {
        if (! $owner instanceof User || ! $contact instanceof EntityContactData) {
            return;
        }

        User::withoutAuditing(static fn (): bool => $owner->forceFill([
            'name' => $contact->authorizedPersonName,
            'phone' => $contact->phone?->value,
        ])->save());
    }

    private function writeConsent(Entity $entity, ?bool $consents): void
    {
        if ($consents !== null) {
            $this->consent->record($entity, $consents);
        }
    }

    private function recordHistory(Entity $entity, EntityProfileChangeSet $changes, ?User $actor): void
    {
        foreach ($changes->after as $field => $newValue) {
            EntityProfileChange::query()->create([
                'entity_id' => $entity->id,
                'changed_by_user_id' => $actor?->id,
                'field' => $field,
                'old_value' => $changes->before[$field] ?? null,
                'new_value' => $newValue,
            ]);
        }
    }
}
