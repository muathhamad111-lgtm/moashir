<?php

declare(strict_types=1);

use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Entities\ProfileSection;
use App\Enums\Entities\ProfileSectionField;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\Entities\ProfileFieldValues;
use App\Support\Entities\ProfileSectionRevision;
use App\Support\ValueObjects\ProfileCompletion;

/**
 * @param  array<string, mixed>  $overrides
 */
function namaaProfile(array $overrides = [], string $ownerName = 'سارة بنت محمد العتيبي'): ProfileFieldValues
{
    $entity = new Entity;
    $entity->forceFill([
        'name' => ['ar' => 'مؤسسة نماء للتنمية', 'en' => 'Namaa Development Foundation'],
        'entity_type' => EntityType::CivilFoundation,
        'development_sector' => DevelopmentSector::Social,
        'org_size' => OrgSize::Medium,
        'founding_year' => 2015,
        'region_id' => 1,
        'city_id' => 4,
        'governance_grade' => '82.50',
        'has_governance_grade' => true,
        'employees_count' => 14,
        'annual_beneficiaries_count' => 3200,
        'has_strategic_plan' => true,
        'unified_number' => '7001234567',
        'license_number' => '770000123',
        'founded_on' => '2015-03-01',
        'website_url' => null,
        'address_building_number' => '2154',
        'address_street' => 'شارع الأمير سلطان',
        'address_district' => 'حي الروضة',
        'address_city' => 'جدة',
        'address_postal_code' => '23435',
        'address_additional_number' => '8027',
        'vat_registered' => false,
        ...$overrides,
    ]);
    $entity->setRelation('owner', new User()->forceFill(['name' => $ownerName, 'email' => 'info@namaa.example.sa']));

    return ProfileFieldValues::of($entity);
}

/**
 * @return array<string, null>
 */
function namaaMockGaps(): array
{
    return [
        'unified_number' => null, 'license_number' => null, 'founded_on' => null,
        'governance_grade' => null, 'has_governance_grade' => null, 'employees_count' => null, 'annual_beneficiaries_count' => null,
    ];
}

it('reports the design mock at 72 percent with three missing fields in identity and governance', function (): void {
    expect(ProfileCompletion::of(namaaProfile(namaaMockGaps()))->toArray())->toBe([
        'percent' => 72,
        'counted' => 22,
        'completed_count' => 16,
        'sections' => [
            ['key' => 'identity', 'state' => 'incomplete', 'missing_count' => 3, 'missing_fields' => ['unified_number', 'license_number', 'founded_on']],
            ['key' => 'classification', 'state' => 'complete', 'missing_count' => 0, 'missing_fields' => []],
            ['key' => 'governance', 'state' => 'incomplete', 'missing_count' => 3, 'missing_fields' => ['has_governance_grade', 'employees_count', 'annual_beneficiaries_count']],
            ['key' => 'contact', 'state' => 'complete', 'missing_count' => 0, 'missing_fields' => []],
            ['key' => 'billing', 'state' => 'complete', 'missing_count' => 0, 'missing_fields' => []],
        ],
    ]);
});

it('floors the share of the filled counted fields', function (array $overrides, int $percent, int $completed, bool $isComplete): void {
    $completion = ProfileCompletion::of(namaaProfile($overrides));

    expect($completion->percent())->toBe($percent)
        ->and($completion->completedCount())->toBe($completed)
        ->and($completion->isComplete())->toBe($isComplete);
})->with([
    'every counted field' => [[], 100, 23, true],
    'governance saved after the mock' => [['unified_number' => null, 'license_number' => null, 'founded_on' => null], 86, 20, false],
    'legacy record without a unified number' => [['unified_number' => null], 95, 22, false],
    'twenty-two of twenty-three floors to 95' => [['employees_count' => null], 95, 22, false],
    'no billing data yet' => [['address_building_number' => null, 'address_street' => null, 'address_district' => null, 'address_city' => null, 'address_postal_code' => null, 'address_additional_number' => null, 'vat_registered' => null], 69, 16, false],
]);

it('counts the VAT number and certificate only when the entity is registered for VAT', function (): void {
    $registered = ProfileCompletion::of(namaaProfile(['vat_registered' => true, 'vat_number' => '310123456700003']));
    $notRegistered = ProfileCompletion::of(namaaProfile(['vat_registered' => false]));

    expect($registered->counted())->toBe(25)
        ->and($registered->missingIn(ProfileSection::Billing))->toBe([ProfileSectionField::VatCertificate])
        ->and($registered->isSectionComplete(ProfileSection::Billing))->toBeFalse()
        ->and(ProfileCompletion::of(namaaProfile(['vat_registered' => true, 'vat_certificate_file_id' => 91]))->missingIn(ProfileSection::Billing))->toBe([ProfileSectionField::VatNumber])
        ->and($notRegistered->counted())->toBe(23)
        ->and($notRegistered->isSectionComplete(ProfileSection::Billing))->toBeTrue();
});

it('counts the governance grade only when the entity answers that it has one', function (?bool $answer, ?string $grade, int $counted, array $missing): void {
    $completion = ProfileCompletion::of(namaaProfile(['has_governance_grade' => $answer, 'governance_grade' => $grade]));

    expect($completion->counted())->toBe($counted)
        ->and($completion->missingIn(ProfileSection::Governance))->toBe($missing);
})->with([
    'yes with a grade' => [true, '64.25', 23, []],
    'yes without a grade yet' => [true, null, 23, [ProfileSectionField::GovernanceGrade]],
    'no grade' => [false, null, 22, []],
    'not answered' => [null, null, 22, [ProfileSectionField::HasGovernanceGrade]],
]);

it('treats a year-only founding record as a missing founding date', function (): void {
    $completion = ProfileCompletion::of(namaaProfile(['founded_on' => null, 'founding_year' => 2015]));

    expect($completion->missingIn(ProfileSection::Identity))->toBe([ProfileSectionField::FoundedOn])
        ->and($completion->isComplete())->toBeFalse();
});

it('counts an unanswered strategic plan as missing and a "no" answer as filled', function (?bool $answer, bool $missing): void {
    $completion = ProfileCompletion::of(namaaProfile(['has_strategic_plan' => $answer]));

    expect(in_array(ProfileSectionField::HasStrategicPlan, $completion->missingIn(ProfileSection::Governance), true))->toBe($missing);
})->with([
    'unanswered' => [null, true],
    'no plan' => [false, false],
    'has a plan' => [true, false],
]);

it('counts zero as a filled governance number and a blank owner name as missing', function (): void {
    expect(ProfileCompletion::of(namaaProfile(['governance_grade' => '0.00', 'employees_count' => 0]))->isComplete())->toBeTrue()
        ->and(ProfileCompletion::of(namaaProfile([], '   '))->missingIn(ProfileSection::Contact))->toBe([ProfileSectionField::AuthorizedPersonName]);
});

it('never counts the optional fields', function (): void {
    $completion = ProfileCompletion::of(namaaProfile(['name' => ['ar' => 'مؤسسة نماء للتنمية', 'en' => null], 'website_url' => null]));

    expect($completion->isComplete())->toBeTrue()
        ->and(fn (): ProfileCompletion => new ProfileCompletion([ProfileSectionField::Phone], []))->toThrow(InvalidArgumentException::class)
        ->and(fn (): ProfileCompletion => new ProfileCompletion([ProfileSectionField::NameAr], [ProfileSectionField::VatNumber]))->toThrow(InvalidArgumentException::class);
});

it('fingerprints a section on its own editable values only', function (): void {
    $base = namaaProfile();
    $governance = ProfileSectionRevision::of($base, ProfileSection::Governance);

    expect(ProfileSectionRevision::of(namaaProfile(), ProfileSection::Governance))->toBe($governance)
        ->and($governance)->toMatch('/^[0-9a-f]{32}$/')
        ->and(ProfileSectionRevision::of(namaaProfile(['employees_count' => 15]), ProfileSection::Governance))->not->toBe($governance)
        ->and(ProfileSectionRevision::of(namaaProfile(['license_number' => '770000999']), ProfileSection::Governance))->toBe($governance)
        ->and(ProfileSectionRevision::of($base, ProfileSection::Identity))->not->toBe(ProfileSectionRevision::of($base, ProfileSection::Classification));
});

it('leaves the official e-mail out of the contact fingerprint', function (): void {
    $values = namaaProfile();
    $other = namaaProfile();
    (fn () => $this->owner?->forceFill(['email' => 'office@namaa.example.sa']))->call($other);

    expect(ProfileSectionRevision::of($other, ProfileSection::Contact))->toBe(ProfileSectionRevision::of($values, ProfileSection::Contact));
});

it('lists the missing counted fields in profile order', function (): void {
    $completion = ProfileCompletion::of(namaaProfile(['license_number' => null, 'employees_count' => null, 'founded_on' => null]));

    expect($completion->missingFields())->toBe([ProfileSectionField::LicenseNumber, ProfileSectionField::FoundedOn, ProfileSectionField::EmployeesCount])
        ->and(ProfileCompletion::of(namaaProfile())->missingFields())->toBe([]);
});

it('labels every counted profile field in both languages', function (ProfileSectionField $field): void {
    expect(__($field->labelKey(), [], 'ar'))->not->toBe($field->labelKey())
        ->and(__($field->labelKey(), [], 'en'))->not->toBe($field->labelKey());
})->with(ProfileSectionField::counted());
