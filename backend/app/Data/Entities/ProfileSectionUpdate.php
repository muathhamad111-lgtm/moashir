<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\ProfileSection;

final readonly class ProfileSectionUpdate
{
    public function __construct(
        public ProfileSection $section,
        public string $baseRevision,
        public EntityProfileWrite $write,
    ) {}
}
