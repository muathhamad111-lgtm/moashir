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

    /**
     * @return array{level: string, valid_until: string|null, validity: string|null, days_until_expiry: int|null}|null
     */
    public static function lastLevelOf(Entity $entity): ?array
    {
        $certificate = $entity->lastLevelCertificate;

        if (! $certificate instanceof Certificate) {
            return null;
        }

        return [
            'level' => EntityLevel::ofCertificate($certificate->level)->value,
            'valid_until' => $certificate->valid_until?->toDateString(),
            ...CertificateValidityPresenter::present($certificate),
        ];
    }

    public static function hasOpenAccreditedRequest(Entity $entity): bool
    {
        return (bool) $entity->getAttribute(EntityLevelQuery::OPEN_ACCREDITED_REQUEST);
    }
}
