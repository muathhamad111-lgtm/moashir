<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\ProfileSection;
use App\Models\Entities\Entity;

final readonly class ProfileSectionSaved
{
    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public Entity $entity,
        public ProfileSection $section,
        public array $changedFields,
    ) {}
}
