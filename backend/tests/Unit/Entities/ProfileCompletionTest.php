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
        'employees_count' => 14,
        'annual_beneficiaries_count' => 3200,
        'has_strategic_plan' => true,
        'unified_number' => '7001234567',
        'license_number' => '770000123',
        'founded_on' => '2015-03-01',
        'website_url' => null,
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
        'governance_grade' => null, 'employees_count' => null, 'annual_beneficiaries_count' => null,
    ];
}

it('reports the design mock at 60 percent with three missing fields in identity and governance', function (): void {
    expect(ProfileCompletion::of(namaaProfile(namaaMockGaps()))->toArray())->toBe([
        'percent' => 60,
        'counted' => 15,
        'completed_count' => 9,
        'sections' => [
            ['key' => 'identity', 'state' => 'incomplete', 'missing_count' => 3, 'missing_fields' => ['unified_number', 'license_number', 'founded_on']],
            ['key' => 'classification', 'state' => 'complete', 'missing_count' => 0, 'missing_fields' => []],
            ['key' => 'governance', 'state' => 'incomplete', 'missing_count' => 3, 'missing_fields' => ['governance_grade', 'employees_count', 'annual_beneficiaries_count']],
            ['key' => 'contact', 'state' => 'complete', 'missing_count' => 0, 'missing_fields' => []],
        ],
    ]);
});

it('floors the share of the filled counted fields', function (array $overrides, int $percent, int $completed, bool $isComplete): void {
    $completion = ProfileCompletion::of(namaaProfile($overrides));

    expect($completion->percent())->toBe($percent)
        ->and($completion->completedCount())->toBe($completed)
        ->and($completion->isComplete())->toBe($isComplete);
})->with([
    'every counted field' => [[], 100, 15, true],
    'governance saved after the mock' => [['unified_number' => null, 'license_number' => null, 'founded_on' => null], 80, 12, false],
    'legacy record without a unified number' => [['unified_number' => null], 93, 14, false],
    'fourteen of fifteen floors to 93' => [['employees_count' => null], 93, 14, false],
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
        ->and(fn (): ProfileCompletion => new ProfileCompletion([ProfileSectionField::Phone]))->toThrow(InvalidArgumentException::class);
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
