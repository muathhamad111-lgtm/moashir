<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;

final readonly class EntityLogoImage
{
    public function __construct(private StoredFileDisk $disk) {}

    public function dataUriOf(Entity $entity): ?string
    {
        if ($entity->logo_file_id === null) {
            return null;
        }

        $logo = StoredFile::query()->whereKey($entity->logo_file_id)->ofPurpose(StoredFilePurpose::EntityLogo)->clean()->first();
        $contents = $logo instanceof StoredFile ? $this->disk->contents($logo) : null;

        return $logo instanceof StoredFile && $contents !== null
            ? sprintf('data:%s;base64,%s', $logo->mime_type, base64_encode($contents))
            : null;
    }
}
