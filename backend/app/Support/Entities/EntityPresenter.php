<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;

final class EntityPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Entity $entity): array
    {
        return [
            'id' => $entity->id,
            'reference' => $entity->reference(),
            'name' => $entity->name->toArray(),
            'status' => $entity->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profile(Entity $entity): array
    {
        return [
            'entity_type' => $entity->entity_type->value,
            'development_sector' => $entity->development_sector->value,
            'org_size' => $entity->org_size->value,
            'founding_year' => $entity->founding_year,
            'region' => self::place($entity->region),
            'city' => self::place($entity->city),
            'governance_grade' => $entity->governance_grade,
            'employees_count' => $entity->employees_count,
            'annual_beneficiaries_count' => $entity->annual_beneficiaries_count,
            'has_strategic_plan' => $entity->has_strategic_plan,
            'profile_completed' => $entity->hasCompleteProfile(),
            'verified_at' => $entity->verified_at?->toIso8601String(),
            'created_at' => $entity->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, code: string, name: array{ar: string, en: string}}
     */
    public static function place(Region|City $place): array
    {
        return ['id' => $place->id, 'code' => $place->code, 'name' => $place->name->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function contact(Entity $entity): array
    {
        return [
            'authorized_person_name' => $entity->owner?->name,
            'official_email' => $entity->owner?->email,
            'phone' => $entity->owner?->phone,
        ];
    }
}
