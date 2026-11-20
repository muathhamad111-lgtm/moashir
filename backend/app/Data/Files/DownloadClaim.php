<?php

declare(strict_types=1);

namespace App\Data\Files;

use App\Enums\Files\AccessIntent;

final readonly class DownloadClaim
{
    public function __construct(public int $boundUserId, public AccessIntent $intent) {}
}
