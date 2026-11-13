<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityProfileWrite;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileSection;
use App\Support\Entities\EntityProfileInput;

final class UpdateGovernanceSectionRequest extends ProfileSectionRequest
{
    public function section(): ProfileSection
    {
        return ProfileSection::Governance;
    }

    protected function sectionRules(): array
    {
        return EntityProfileInput::governanceRules();
    }

    protected function write(array $validated): EntityProfileWrite
    {
        return new EntityProfileWrite([
            EntityProfileField::GovernanceGrade->value => EntityProfileInput::governanceGrade($validated),
            EntityProfileField::EmployeesCount->value => EntityProfileInput::optionalCount($validated, EntityProfileField::EmployeesCount->value),
            EntityProfileField::AnnualBeneficiariesCount->value => EntityProfileInput::optionalCount($validated, EntityProfileField::AnnualBeneficiariesCount->value),
            EntityProfileField::HasStrategicPlan->value => EntityProfileInput::strategicPlan($validated),
        ]);
    }
}
