<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\EntityProfileField;
use App\Support\ValueObjects\LicenseNumber;
use App\Support\ValueObjects\UnifiedNumber;
use App\Support\ValueObjects\WebsiteUrl;
use Carbon\CarbonImmutable;

final readonly class EntityRegistryUpdate
{
    /**
     * @param  list<EntityProfileField>  $provided
     */
    public function __construct(
        public ?LicenseNumber $licenseNumber = null,
        public ?CarbonImmutable $foundedOn = null,
        public ?WebsiteUrl $websiteUrl = null,
        public array $provided = [],
        public ?UnifiedNumber $unifiedNumber = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $values = [
            EntityProfileField::UnifiedNumber->value => $this->unifiedNumber?->value,
            EntityProfileField::LicenseNumber->value => $this->licenseNumber?->value,
            EntityProfileField::FoundedOn->value => $this->foundedOn,
            EntityProfileField::WebsiteUrl->value => $this->websiteUrl?->value,
        ];

        return array_intersect_key($values, array_flip(array_map(static fn (EntityProfileField $field): string => $field->value, $this->provided)));
    }
}
