<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileChangeSet;
use App\Data\Entities\EntityProfileData;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Identity\User;
use App\Support\Entities\EntityUniqueViolation;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class EntityProfileWriter
{
    public function __construct(private EntityProfileDiff $diff) {}

    public function write(Entity $entity, EntityProfileData $profile, EntityContactData $contact, ?User $actor): EntityProfileChangeSet
    {
        $changes = $this->diff->between($entity, $profile);

        $entity->fill($entity->profileAttributesFrom($profile));
        $entity->setRelation('owner', $this->writeContact($entity, $contact));
        $entity->syncProfileCompletedAt();
        $this->save($entity);

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

    private function writeContact(Entity $entity, EntityContactData $contact): ?User
    {
        $owner = User::query()->where('entity_id', $entity->id)->first();

        if ($owner instanceof User) {
            User::withoutAuditing(static fn (): bool => $owner->forceFill([
                'name' => $contact->authorizedPersonName,
                'phone' => $contact->phone?->value,
            ])->save());
        }

        return $owner;
    }
}
