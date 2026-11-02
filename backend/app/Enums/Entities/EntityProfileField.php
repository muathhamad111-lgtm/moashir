<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum EntityProfileField: string
{
    case Name = 'name';
    case EntityType = 'entity_type';
    case DevelopmentSector = 'development_sector';
    case OrgSize = 'org_size';
    case FoundingYear = 'founding_year';
    case Region = 'region_id';
    case City = 'city_id';
    case GovernanceGrade = 'governance_grade';
    case EmployeesCount = 'employees_count';
    case AnnualBeneficiariesCount = 'annual_beneficiaries_count';
    case HasStrategicPlan = 'has_strategic_plan';

    public function labelKey(): string
    {
        return 'entities.profile_fields.'.$this->value;
    }
}
