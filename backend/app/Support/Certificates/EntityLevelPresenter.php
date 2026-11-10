<?php

declare(strict_types=1);

namespace App\Support\Certificates;

use App\Enums\Certificates\EntityLevel;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;

final class EntityLevelPresenter
{
    /**
     * @return array{level: string|null, level_certificate_number: string|null, level_valid_until: string|null}
     */
    public static function of(Entity $entity): array
    {
        return [
            'level' => self::levelOf($entity)?->value,
            'level_certificate_number' => $entity->levelCertificate?->number,
            'level_valid_until' => $entity->levelCertificate?->valid_until?->toDateString(),
        ];
    }

    public static function levelOf(Entity $entity): ?EntityLevel
    {
        $certificate = $entity->levelCertificate;

        return $certificate instanceof Certificate ? EntityLevel::ofCertificate($certificate->level) : null;
    }

    public static function hasOpenAccreditedRequest(Entity $entity): bool
    {
        return (bool) $entity->getAttribute(EntityLevelQuery::OPEN_ACCREDITED_REQUEST);
    }
}
