<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Contracts\Entities\EntityLogoRetention;
use App\Models\Certificates\Certificate;

final class CertificateLogoRetention implements EntityLogoRetention
{
    public function isRetained(int $storedFileId): bool
    {
        return Certificate::query()->where('entity_logo_file_id', $storedFileId)->exists();
    }
}
