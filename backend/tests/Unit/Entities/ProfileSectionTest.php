<?php

declare(strict_types=1);

use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileFieldRequirement;
use App\Enums\Entities\ProfileSection;
use App\Enums\Entities\ProfileSectionField;

it('lists the five sections and their fields in display order', function (): void {
    $fields = array_combine(
        array_map(static fn (ProfileSection $section): string => $section->value, ProfileSection::cases()),
        array_map(static fn (ProfileSection $section): array => array_map(static fn (ProfileSectionField $field): string => $field->value, $section->fields()), ProfileSection::cases()),
    );

    expect($fields)->toBe([
        'identity' => ['name.ar', 'name.en', 'unified_number', 'license_number', 'founded_on'],
        'classification' => ['entity_type', 'development_sector', 'org_size', 'region_id', 'city_id'],
        'governance' => ['governance_grade', 'employees_count', 'annual_beneficiaries_count', 'has_strategic_plan'],
        'contact' => ['official_email', 'authorized_person_name', 'phone', 'website_url', 'marketing_consent'],
        'billing' => [
            'address_building_number', 'address_street', 'address_district', 'address_city', 'address_postal_code',
            'address_additional_number', 'address_short_code', 'vat_registered', 'vat_number', 'vat_certificate',
        ],
    ]);
});

it('defines one requirement level per field', function (ProfileSectionField $field, ProfileFieldRequirement $requirement): void {
    expect($field->requirement())->toBe($requirement)
        ->and($field->isCounted())->toBe($requirement !== ProfileFieldRequirement::Optional);
})->with([
    [ProfileSectionField::NameAr, ProfileFieldRequirement::Required],
    [ProfileSectionField::NameEn, ProfileFieldRequirement::Optional],
    [ProfileSectionField::UnifiedNumber, ProfileFieldRequirement::Required],
    [ProfileSectionField::LicenseNumber, ProfileFieldRequirement::Completion],
    [ProfileSectionField::FoundedOn, ProfileFieldRequirement::Completion],
    [ProfileSectionField::EntityType, ProfileFieldRequirement::Required],
    [ProfileSectionField::DevelopmentSector, ProfileFieldRequirement::Required],
    [ProfileSectionField::OrgSize, ProfileFieldRequirement::Required],
    [ProfileSectionField::Region, ProfileFieldRequirement::Required],
    [ProfileSectionField::City, ProfileFieldRequirement::Required],
    [ProfileSectionField::GovernanceGrade, ProfileFieldRequirement::Completion],
    [ProfileSectionField::EmployeesCount, ProfileFieldRequirement::Completion],
    [ProfileSectionField::AnnualBeneficiariesCount, ProfileFieldRequirement::Completion],
    [ProfileSectionField::HasStrategicPlan, ProfileFieldRequirement::Completion],
    [ProfileSectionField::OfficialEmail, ProfileFieldRequirement::Required],
    [ProfileSectionField::AuthorizedPersonName, ProfileFieldRequirement::Required],
    [ProfileSectionField::Phone, ProfileFieldRequirement::Optional],
    [ProfileSectionField::WebsiteUrl, ProfileFieldRequirement::Optional],
    [ProfileSectionField::MarketingConsent, ProfileFieldRequirement::Optional],
    [ProfileSectionField::AddressBuildingNumber, ProfileFieldRequirement::Required],
    [ProfileSectionField::AddressStreet, ProfileFieldRequirement::Required],
    [ProfileSectionField::AddressDistrict, ProfileFieldRequirement::Required],
    [ProfileSectionField::AddressCity, ProfileFieldRequirement::Required],
    [ProfileSectionField::AddressPostalCode, ProfileFieldRequirement::Required],
    [ProfileSectionField::AddressAdditionalNumber, ProfileFieldRequirement::Required],
    [ProfileSectionField::AddressShortCode, ProfileFieldRequirement::Optional],
    [ProfileSectionField::VatRegistered, ProfileFieldRequirement::Required],
    [ProfileSectionField::VatNumber, ProfileFieldRequirement::Required],
    [ProfileSectionField::VatCertificate, ProfileFieldRequirement::Required],
]);

it('can count twenty-four fields and edits every field but the official e-mail and the VAT certificate in its section', function (): void {
    expect(ProfileSectionField::counted())->toHaveCount(24)
        ->and(array_map(static fn (ProfileSectionField $field): string => $field->value, ProfileSection::Contact->editableFields()))
        ->toBe(['authorized_person_name', 'phone', 'website_url', 'marketing_consent'])
        ->and(array_map(static fn (ProfileSectionField $field): string => $field->value, ProfileSection::Billing->editableFields()))
        ->not->toContain('vat_certificate');
});

it('makes only the VAT number and certificate depend on the VAT registration answer', function (): void {
    $dependent = array_values(array_filter(ProfileSectionField::cases(), static fn (ProfileSectionField $field): bool => $field->dependsOnVatRegistration()));

    expect($dependent)->toBe([ProfileSectionField::VatNumber, ProfileSectionField::VatCertificate]);
});

it('maps every history field to one section', function (): void {
    $history = array_map(
        static fn (ProfileSection $section): array => array_map(static fn (EntityProfileField $field): string => $field->value, $section->historyFields()),
        ProfileSection::cases(),
    );

    expect($history)->toBe([
        ['name', 'founding_year', 'license_number', 'unified_number', 'founded_on'],
        ['entity_type', 'development_sector', 'org_size', 'region_id', 'city_id'],
        ['governance_grade', 'employees_count', 'annual_beneficiaries_count', 'has_strategic_plan'],
        ['website_url', 'authorized_person_name', 'phone', 'marketing_consent'],
        [
            'address_building_number', 'address_street', 'address_district', 'address_city', 'address_postal_code',
            'address_additional_number', 'address_short_code', 'vat_registered', 'vat_number',
        ],
    ])->and(array_merge(...$history))->toHaveCount(count(EntityProfileField::cases()));
});

it('keeps the personal contact fields apart from the entity record', function (): void {
    $personal = array_values(array_filter(EntityProfileField::cases(), static fn (EntityProfileField $field): bool => $field->holdsPersonalData()));
    $outside = array_values(array_filter(EntityProfileField::cases(), static fn (EntityProfileField $field): bool => ! $field->isEntityRecord()));

    expect($personal)->toBe([EntityProfileField::AuthorizedPersonName, EntityProfileField::Phone])
        ->and($outside)->toBe([EntityProfileField::AuthorizedPersonName, EntityProfileField::Phone, EntityProfileField::MarketingConsent]);
});
