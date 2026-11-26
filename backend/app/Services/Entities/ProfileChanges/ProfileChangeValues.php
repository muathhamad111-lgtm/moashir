<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileWrite;
use App\Enums\Entities\EntityProfileField;
use App\Models\Entities\Entity;
use App\Support\Entities\ComparableProfileValue;

/**
 * Reads profile values in the one plain form a change request stores: the values a write proposes, normalized through
 * the model's casts, and the approved values they would replace.
 */
final class ProfileChangeValues
{
    /**
     * @return array<string, mixed>
     */
    public function proposedBy(Entity $entity, EntityProfileWrite $write): array
    {
        $probe = (clone $entity)->forceFill($write->attributes);
        $values = [];

        foreach (array_keys($write->attributes) as $field) {
            $values[$field] = ComparableProfileValue::of($probe->getAttribute($field));
        }

        if ($write->contact instanceof EntityContactData) {
            $values[EntityProfileField::AuthorizedPersonName->value] = $write->contact->authorizedPersonName;
            $values[EntityProfileField::Phone->value] = $write->contact->phone?->value;
        }

        return $values;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function approvedOf(Entity $entity, array $fields): array
    {
        $owner = $entity->loadMissing('owner')->owner;
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = match ($field) {
                EntityProfileField::AuthorizedPersonName->value => $owner?->name,
                EntityProfileField::Phone->value => $owner?->phone,
                default => ComparableProfileValue::of($entity->getAttribute($field)),
            };
        }

        return $values;
    }
}
