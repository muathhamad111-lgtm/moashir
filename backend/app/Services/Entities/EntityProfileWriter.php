<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileChangeSet;
use App\Data\Entities\EntityProfileData;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final readonly class EntityProfileWriter
{
    public function __construct(private EntityProfileDiff $diff) {}

    public function write(Entity $entity, EntityProfileData $profile, EntityContactData $contact, ?User $actor): EntityProfileChangeSet
    {
        $changes = $this->diff->between($entity, $profile);

        $entity->fill($profile->toAttributes());
        $entity->profile_completed_at = $profile->isComplete() ? ($entity->profile_completed_at ?? Date::now()) : null;
        $entity->save();

        $this->recordHistory($entity, $changes, $actor);
        $this->writeContact($entity, $contact);

        return $changes;
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

    private function writeContact(Entity $entity, EntityContactData $contact): void
    {
        $owner = User::query()->where('entity_id', $entity->id)->first();

        if ($owner instanceof User) {
            User::withoutAuditing(static fn (): bool => $owner->forceFill([
                'name' => $contact->authorizedPersonName,
                'phone' => $contact->phone?->value,
            ])->save());
        }
    }
}
