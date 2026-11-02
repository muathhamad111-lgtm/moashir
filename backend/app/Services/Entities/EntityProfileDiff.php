<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityProfileChangeSet;
use App\Data\Entities\EntityProfileData;
use App\Models\Entities\Entity;
use App\Support\ValueObjects\EntityName;
use BackedEnum;

final class EntityProfileDiff
{
    public function between(Entity $entity, EntityProfileData $profile): EntityProfileChangeSet
    {
        $before = [];
        $after = [];

        foreach ($profile->toAttributes() as $field => $value) {
            $previous = $this->comparable($entity->getAttribute($field));
            $next = $this->comparable($value);

            if ($previous !== $next) {
                $before[$field] = $previous;
                $after[$field] = $next;
            }
        }

        return new EntityProfileChangeSet($before, $after);
    }

    private function comparable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof EntityName => $value->toArray(),
            $value instanceof BackedEnum => $value->value,
            default => $value,
        };
    }
}
