<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityProfileWrite;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileSection;
use App\Support\Entities\EntityProfileInput;
use App\Support\ScalarValue;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\UnifiedNumber;

final class UpdateIdentitySectionRequest extends ProfileSectionRequest
{
    public function section(): ProfileSection
    {
        return ProfileSection::Identity;
    }

    protected function sectionRules(): array
    {
        $entityId = $this->user()?->entity_id;

        return [
            ...EntityProfileInput::nameRules(),
            'unified_number' => EntityProfileInput::unifiedNumberRules($entityId),
            'license_number' => EntityProfileInput::licenseNumberRules($entityId),
            'founded_on' => EntityProfileInput::foundedOnRules(),
        ];
    }

    protected function write(array $validated): EntityProfileWrite
    {
        $name = is_array($validated['name'] ?? null) ? $validated['name'] : [];

        return new EntityProfileWrite([
            EntityProfileField::Name->value => EntityName::fromArray($name),
            EntityProfileField::UnifiedNumber->value => new UnifiedNumber(ScalarValue::string($validated['unified_number'] ?? null))->value,
            EntityProfileField::LicenseNumber->value => EntityProfileInput::licenseNumber($validated)?->value,
            EntityProfileField::FoundedOn->value => EntityProfileInput::foundedOn($validated),
        ]);
    }
}
