<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Models\Entities\Entity;

final readonly class CertificateEntityLogo
{
    /**
     * The entity's logo at issue time, kept on the certificate so a later logo change never alters it.
     */
    public function currentFileIdOf(int $entityId): ?int
    {
        $logoFileId = Entity::withTrashed()->whereKey($entityId)->sharedLock()->value('logo_file_id');

        return is_int($logoFileId) ? $logoFileId : null;
    }
}
