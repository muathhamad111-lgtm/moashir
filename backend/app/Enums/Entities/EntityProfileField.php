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
    case LicenseNumber = 'license_number';
    case UnifiedNumber = 'unified_number';
    case FoundedOn = 'founded_on';
    case WebsiteUrl = 'website_url';

    public function isBasicData(): bool
    {
        return in_array($this, [self::Name, self::EntityType, self::DevelopmentSector, self::OrgSize, self::FoundingYear, self::Region, self::City], true);
    }

    /**
     * @return list<self>
     */
    public static function basicData(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $field): bool => $field->isBasicData()));
    }

    /**
     * @return list<self>
     */
    public static function registryDetails(): array
    {
        return [self::UnifiedNumber, self::LicenseNumber, self::FoundedOn, self::WebsiteUrl];
    }

    public function labelKey(): string
    {
        return 'entities.profile_fields.'.$this->value;
    }
}
