<?php

declare(strict_types=1);

namespace App\Contracts\Entities;

interface EntityLogoRetention
{
    public function isRetained(int $storedFileId): bool;
}
