<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Enums\Entities\EntityAccess;
use App\Http\Resources\Entities\EntityContactResource;
use App\Http\Resources\Entities\EntitySummaryResource;
use App\Http\Resources\Entities\StaffEntityResource;
use App\Models\Entities\Entity;
use App\Support\Certificates\EntityLevelQuery;
use Illuminate\Http\Resources\Json\JsonResource;

final class EntityResourceVariant
{
    /**
     * @return class-string<JsonResource>
     */
    public static function classFor(EntityAccess $access): string
    {
        return match ($access) {
            EntityAccess::Full => StaffEntityResource::class,
            EntityAccess::Contact => EntityContactResource::class,
            EntityAccess::None => EntitySummaryResource::class,
        };
    }

    public static function of(Entity $entity, EntityAccess $access): JsonResource
    {
        $class = self::classFor($access);

        $entity->loadMissing(['owner', 'region', 'city', 'logo', EntityLevelQuery::RELATION]);

        return new $class($entity->loadExists(EntityLevelQuery::openAccreditedRequestFlag()));
    }
}
