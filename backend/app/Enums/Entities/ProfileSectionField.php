<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ProfileSectionField: string
{
    case NameAr = 'name.ar';
    case NameEn = 'name.en';
    case UnifiedNumber = 'unified_number';
    case LicenseNumber = 'license_number';
    case FoundedOn = 'founded_on';
    case EntityType = 'entity_type';
    case DevelopmentSector = 'development_sector';
    case OrgSize = 'org_size';
    case Region = 'region_id';
    case City = 'city_id';
    case GovernanceGrade = 'governance_grade';
    case EmployeesCount = 'employees_count';
    case AnnualBeneficiariesCount = 'annual_beneficiaries_count';
    case HasStrategicPlan = 'has_strategic_plan';
    case OfficialEmail = 'official_email';
    case AuthorizedPersonName = 'authorized_person_name';
    case Phone = 'phone';
    case WebsiteUrl = 'website_url';
    case MarketingConsent = 'marketing_consent';

    public function section(): ProfileSection
    {
        return match ($this) {
            self::NameAr, self::NameEn, self::UnifiedNumber, self::LicenseNumber, self::FoundedOn => ProfileSection::Identity,
            self::EntityType, self::DevelopmentSector, self::OrgSize, self::Region, self::City => ProfileSection::Classification,
            self::GovernanceGrade, self::EmployeesCount, self::AnnualBeneficiariesCount, self::HasStrategicPlan => ProfileSection::Governance,
            self::OfficialEmail, self::AuthorizedPersonName, self::Phone, self::WebsiteUrl, self::MarketingConsent => ProfileSection::Contact,
        };
    }

    public function requirement(): ProfileFieldRequirement
    {
        return match ($this) {
            self::NameAr, self::UnifiedNumber, self::EntityType, self::DevelopmentSector, self::OrgSize, self::Region, self::City,
            self::OfficialEmail, self::AuthorizedPersonName => ProfileFieldRequirement::Required,
            self::LicenseNumber, self::FoundedOn, self::GovernanceGrade, self::EmployeesCount, self::AnnualBeneficiariesCount,
            self::HasStrategicPlan => ProfileFieldRequirement::Completion,
            self::NameEn, self::Phone, self::WebsiteUrl, self::MarketingConsent => ProfileFieldRequirement::Optional,
        };
    }

    public function isCounted(): bool
    {
        return $this->requirement()->isCounted();
    }

    public function isEditable(): bool
    {
        return $this !== self::OfficialEmail;
    }

    /**
     * @return list<self>
     */
    public static function counted(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $field): bool => $field->isCounted()));
    }
}
