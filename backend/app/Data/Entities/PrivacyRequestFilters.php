<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\PrivacyRequestKind;
use App\Enums\Entities\PrivacyRequestStatus;

final readonly class PrivacyRequestFilters
{
    public function __construct(
        public ?PrivacyRequestStatus $status = null,
        public ?PrivacyRequestKind $kind = null,
        public ?int $entityId = null,
        public int $perPage = 15,
    ) {}
}
