<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Certificates\Certificate;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;
use RuntimeException;

final readonly class CertificateEntityLogo
{
    public function __construct(private StoredFileDisk $disk) {}

    public function dataUriOf(Certificate $certificate): ?string
    {
        if ($certificate->entity_logo_file_id === null) {
            return null;
        }

        $logo = StoredFile::query()->whereKey($certificate->entity_logo_file_id)->ofPurpose(StoredFilePurpose::EntityLogo)->clean()->first();
        $contents = $logo instanceof StoredFile ? $this->disk->contents($logo) : null;

        if (! $logo instanceof StoredFile || $contents === null) {
            throw new RuntimeException('The entity logo kept for the certificate is missing.');
        }

        return sprintf('data:%s;base64,%s', $logo->mime_type, base64_encode($contents));
    }
}
