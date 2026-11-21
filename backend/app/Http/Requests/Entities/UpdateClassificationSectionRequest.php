<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityProfileWrite;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Entities\ProfileSection;
use App\Support\Entities\EntityProfileInput;
use App\Support\ScalarValue;

final class UpdateClassificationSectionRequest extends ProfileSectionRequest
{
    public function section(): ProfileSection
    {
        return ProfileSection::Classification;
    }

    protected function sectionRules(): array
    {
        return EntityProfileInput::classificationRules($this->input('region_id'));
    }

    protected function write(array $validated): EntityProfileWrite
    {
        return new EntityProfileWrite([
            EntityProfileField::EntityType->value => EntityType::from(ScalarValue::string($validated['entity_type'] ?? null)),
            EntityProfileField::DevelopmentSector->value => DevelopmentSector::from(ScalarValue::string($validated['development_sector'] ?? null)),
            EntityProfileField::OrgSize->value => OrgSize::from(ScalarValue::string($validated['org_size'] ?? null)),
            EntityProfileField::Region->value => ScalarValue::int($validated['region_id'] ?? null),
            EntityProfileField::City->value => ScalarValue::int($validated['city_id'] ?? null),
        ]);
    }
}
