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
    case HasGovernanceGrade = 'has_governance_grade';
    case GovernanceGrade = 'governance_grade';
    case EmployeesCount = 'employees_count';
    case AnnualBeneficiariesCount = 'annual_beneficiaries_count';
    case HasStrategicPlan = 'has_strategic_plan';
    case OfficialEmail = 'official_email';
    case AuthorizedPersonName = 'authorized_person_name';
    case Phone = 'phone';
    case WebsiteUrl = 'website_url';
    case MarketingConsent = 'marketing_consent';
    case AddressBuildingNumber = 'address_building_number';
    case AddressStreet = 'address_street';
    case AddressDistrict = 'address_district';
    case AddressCity = 'address_city';
    case AddressPostalCode = 'address_postal_code';
    case AddressAdditionalNumber = 'address_additional_number';
    case AddressShortCode = 'address_short_code';
    case VatRegistered = 'vat_registered';
    case VatNumber = 'vat_number';
    case VatCertificate = 'vat_certificate';

    public function section(): ProfileSection
    {
        return match ($this) {
            self::NameAr, self::NameEn, self::UnifiedNumber, self::LicenseNumber, self::FoundedOn => ProfileSection::Identity,
            self::EntityType, self::DevelopmentSector, self::OrgSize, self::Region, self::City => ProfileSection::Classification,
            self::HasGovernanceGrade, self::GovernanceGrade, self::EmployeesCount, self::AnnualBeneficiariesCount, self::HasStrategicPlan => ProfileSection::Governance,
            self::OfficialEmail, self::AuthorizedPersonName, self::Phone, self::WebsiteUrl, self::MarketingConsent => ProfileSection::Contact,
            self::AddressBuildingNumber, self::AddressStreet, self::AddressDistrict, self::AddressCity, self::AddressPostalCode,
            self::AddressAdditionalNumber, self::AddressShortCode, self::VatRegistered, self::VatNumber, self::VatCertificate => ProfileSection::Billing,
        };
    }

    public function requirement(): ProfileFieldRequirement
    {
        return match ($this) {
            self::NameAr, self::UnifiedNumber, self::EntityType, self::DevelopmentSector, self::OrgSize, self::Region, self::City,
            self::HasGovernanceGrade, self::OfficialEmail, self::AuthorizedPersonName, self::AddressBuildingNumber,
            self::AddressStreet, self::AddressDistrict, self::AddressCity, self::AddressPostalCode, self::AddressAdditionalNumber,
            self::VatRegistered, self::VatNumber, self::VatCertificate => ProfileFieldRequirement::Required,
            self::LicenseNumber, self::FoundedOn, self::GovernanceGrade, self::EmployeesCount, self::AnnualBeneficiariesCount,
            self::HasStrategicPlan => ProfileFieldRequirement::Completion,
            self::NameEn, self::Phone, self::WebsiteUrl, self::MarketingConsent, self::AddressShortCode => ProfileFieldRequirement::Optional,
        };
    }

    public function isCounted(): bool
    {
        return $this->requirement()->isCounted();
    }

    public function isEditable(): bool
    {
        return $this !== self::OfficialEmail && $this !== self::VatCertificate;
    }

    /**
     * The yes/no answer this field depends on: it only counts, and only shows, when that answer is yes.
     */
    public function gate(): ?self
    {
        return match ($this) {
            self::VatNumber, self::VatCertificate => self::VatRegistered,
            self::GovernanceGrade => self::HasGovernanceGrade,
            default => null,
        };
    }

    public function labelKey(): string
    {
        return 'entities.profile_fields.'.match ($this) {
            self::NameAr, self::NameEn => 'name',
            default => $this->value,
        };
    }

    /**
     * @return list<self>
     */
    public static function counted(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $field): bool => $field->isCounted()));
    }
}
