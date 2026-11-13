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
    case AuthorizedPersonName = 'authorized_person_name';
    case Phone = 'phone';
    case MarketingConsent = 'marketing_consent';

    public function section(): ProfileSection
    {
        return match ($this) {
            self::Name, self::UnifiedNumber, self::LicenseNumber, self::FoundedOn, self::FoundingYear => ProfileSection::Identity,
            self::EntityType, self::DevelopmentSector, self::OrgSize, self::Region, self::City => ProfileSection::Classification,
            self::GovernanceGrade, self::EmployeesCount, self::AnnualBeneficiariesCount, self::HasStrategicPlan => ProfileSection::Governance,
            self::AuthorizedPersonName, self::Phone, self::WebsiteUrl, self::MarketingConsent => ProfileSection::Contact,
        };
    }

    public function isEntityRecord(): bool
    {
        return ! in_array($this, [self::AuthorizedPersonName, self::Phone, self::MarketingConsent], true);
    }

    public function holdsPersonalData(): bool
    {
        return $this === self::AuthorizedPersonName || $this === self::Phone;
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
