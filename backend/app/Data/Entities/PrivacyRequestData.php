<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\PrivacyRequestKind;

final readonly class PrivacyRequestData
{
    public function __construct(
        public int $entityId,
        public PrivacyRequestKind $kind,
        public string $details,
    ) {}
}
