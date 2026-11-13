<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Http\Resources\Entities\EntityLogoResource;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Models\Files\StoredFile;
use Illuminate\Http\Request;

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
     * @return array{unified_number: string|null, license_number: string|null, founded_on: string|null, website_url: string|null, logo: array{id: string, url: string|null, uploaded_at: string}|null}
     */
    public static function registry(Entity $entity, Request $request): array
    {
        return [
            'unified_number' => $entity->unified_number,
            'license_number' => $entity->license_number,
            'founded_on' => $entity->founded_on?->toDateString(),
            'website_url' => $entity->website_url,
            'logo' => $entity->logo instanceof StoredFile ? new EntityLogoResource($entity->logo)->toArray($request) : null,
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
            'phone_verified' => $entity->owner?->phone_verified_at !== null,
        ];
    }
}
