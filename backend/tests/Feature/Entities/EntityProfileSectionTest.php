<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileSection;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Entities\EntityProfileChange;
use App\Notifications\Entities\EntityNotification;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function historyOf(int $entityId): array
{
    return EntityProfileChange::query()->where('entity_id', $entityId)->get()
        ->mapWithKeys(static fn (EntityProfileChange $change): array => [$change->field->value => [$change->old_value, $change->new_value]])
        ->all();
}

it('applies an approved governance section, records every changed field and tells the entity', function (): void {
    $admin = systemAdmin();
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->incompleteProfile());
    $owner = ownerOf($entity);

    saveAndApproveSection($entity, ProfileSection::Governance, ['governance_grade' => '91.5', 'employees_count' => 60, 'annual_beneficiaries_count' => 3200, 'has_strategic_plan' => null])
        ->assertOk()
        ->assertJsonPath('data.governance_grade', '91.50')
        ->assertJsonPath('data.employees_count', 60)
        ->assertJsonPath('data.has_strategic_plan', null)
        ->assertJsonPath('data.profile_completion.percent', 95)
        ->assertJsonPath('data.profile_completion.completed_count', 21)
        ->assertJsonPath('data.profile_completion.sections.2', ['key' => 'governance', 'state' => 'incomplete', 'missing_count' => 1, 'missing_fields' => ['has_strategic_plan']]);

    expect(historyOf($entity->id))->toBe([
        'governance_grade' => [null, '91.50'],
        'employees_count' => [null, 60],
        'annual_beneficiaries_count' => [null, 3200],
        'has_strategic_plan' => [true, null],
    ])
        ->and(EntityProfileChange::query()->value('changed_by_user_id'))->toBe($owner->id)
        ->and(Audit::query()->where('event', AuditEvent::EntityProfileChangeDecided->value)->sole()->new_values)->toMatchArray(['status' => 'approved', 'governance_grade' => '91.50']);
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileChangeRequested);
    Notification::assertNotSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityCoreDataEdited);
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileChangeDecided);
});

it('saves the identity section and derives the founding year from the founding date', function (): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['founding_year' => 2005, 'founded_on' => null, 'license_number' => null]));

    saveAndApproveSection($entity, ProfileSection::Identity, [
        'name' => ['ar' => 'مؤسسة نماء للتنمية', 'en' => 'Namaa Development Foundation'],
        'license_number' => '000123',
        'founded_on' => '2012-03-01',
    ])->assertOk()
        ->assertJsonPath('data.name', ['ar' => 'مؤسسة نماء للتنمية', 'en' => 'Namaa Development Foundation'])
        ->assertJsonPath('data.license_number', '000123')
        ->assertJsonPath('data.founded_on', '2012-03-01')
        ->assertJsonPath('data.founding_year', 2012);

    expect(historyOf($entity->id))->toHaveKeys(['name', 'license_number', 'founded_on', 'founding_year']);
});

it('keeps the founding year when the founding date is cleared', function (): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['founding_year' => 2012]));

    saveAndApproveSection($entity, ProfileSection::Identity, ['founded_on' => null])->assertOk()
        ->assertJsonPath('data.founded_on', null)
        ->assertJsonPath('data.founding_year', 2012)
        ->assertJsonPath('data.profile_completion.sections.0.missing_fields', ['founded_on']);
});

it('moves the entity to another region and one of its cities', function (): void {
    $entity = entityWithOwner();
    $newCity = regionWithCity();

    saveAndApproveSection($entity, ProfileSection::Classification, ['region_id' => $newCity->region_id, 'city_id' => $newCity->id, 'org_size' => 'large'])
        ->assertOk()
        ->assertJsonPath('data.region.id', $newCity->region_id)
        ->assertJsonPath('data.city.id', $newCity->id)
        ->assertJsonPath('data.org_size', 'large');
});

it('saves the contact section with a masked phone in the history and keeps contact data out of the audit log', function (): void {
    $admin = systemAdmin();
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    saveAndApproveSection($entity, ProfileSection::Contact, [
        'authorized_person_name' => 'ريم بنت خالد الحربي',
        'phone' => '0569876543',
        'website_url' => 'HTTPS://WWW.Alamal.Example.SA/',
        'marketing_consent' => true,
    ])->assertOk()
        ->assertJsonPath('data.authorized_person_name', 'ريم بنت خالد الحربي')
        ->assertJsonPath('data.phone', '+966569876543')
        ->assertJsonPath('data.phone_verified', false)
        ->assertJsonPath('data.website_url', 'https://www.alamal.example.sa')
        ->assertJsonPath('data.marketing_consent', true);

    $history = historyOf($entity->id);
    $audit = Audit::query()->where('event', AuditEvent::EntityProfileChangeDecided->value)->sole();
    $everyAudit = json_encode(Audit::query()->get(['old_values', 'new_values'])->toArray(), JSON_UNESCAPED_UNICODE);

    expect($history)->toEqual([
        'website_url' => [null, 'https://www.alamal.example.sa'],
        'authorized_person_name' => [$owner->name, 'ريم بنت خالد الحربي'],
        'phone' => [null, '+9665*****543'],
        'marketing_consent' => [false, true],
    ])
        ->and($everyAudit)->not->toContain('ريم بنت خالد الحربي')->not->toContain('569876543')
        ->and($audit->new_values['approved'] ?? null)->toBe(['website_url', 'authorized_person_name', 'phone'])
        ->and(Audit::query()->where('event', AuditEvent::EntityProfileUpdated->value)->sole()->new_values['changed_fields'] ?? null)->toBe(['marketing_consent']);
    Notification::assertSentTo($admin, EntityNotification::class);
});

it('raises no core-data notice when an approved change applies', function (): void {
    $admin = systemAdmin();
    $entity = entityWithOwner();

    saveAndApproveSection($entity, ProfileSection::Contact, ['authorized_person_name' => 'ريم بنت خالد الحربي'])->assertOk();

    Notification::assertNotSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityCoreDataEdited);
    expect(Audit::query()->where('event', AuditEvent::EntityProfileUpdated->value)->count())->toBe(0);
});

it('records the withdrawal of marketing consent', function (): void {
    $entity = entityWithOwner();
    $entity->forceFill(['marketing_consent_at' => now()])->save();

    saveSection($entity, ProfileSection::Contact, ['marketing_consent' => false])->assertAccepted()->assertJsonPath('data.marketing_consent', false);

    expect($entity->refresh()->marketing_consent_at)->toBeNull()
        ->and($entity->marketing_unsubscribed_at)->not->toBeNull()
        ->and(historyOf($entity->id))->toBe(['marketing_consent' => [true, false]]);
});

it('writes nothing for a save without a change', function (ProfileSection $section): void {
    $entity = entityWithOwner();

    saveSection($entity, $section)->assertAccepted()->assertJsonPath('data.pending_profile_change', null);

    expect(EntityProfileChange::query()->count())->toBe(0)
        ->and(pendingProfileChangeOf($entity))->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::EntityProfileUpdated->value)->count())->toBe(0);
    Notification::assertNothingSent();
})->with(ProfileSection::cases());

it('refuses a save on a stale revision and writes nothing', function (): void {
    $entity = entityWithOwner();
    $stale = sectionBody($entity, ProfileSection::Governance, ['employees_count' => 5]);
    saveAndApproveSection($entity, ProfileSection::Governance, ['employees_count' => 7])->assertOk();

    $response = switchTo(ownerOf($entity))->patchJson('/api/v1/entities/me/profile/governance', $stale)
        ->assertConflict()
        ->assertJsonPath('code', 'profile.section_stale')
        ->assertJsonPath('message', __('errors.profile.section_stale'))
        ->assertJsonPath('meta.section', 'governance');

    expect($response->json('meta.revision'))->toBe(sectionBody($entity, ProfileSection::Governance)['base_revision'])
        ->and($entity->refresh()->employees_count)->toBe(7)
        ->and(EntityProfileChange::query()->count())->toBe(1);
});

it('detects a staff edit made after the section was opened', function (): void {
    $entity = entityWithOwner();
    $opened = sectionBody($entity, ProfileSection::Governance, ['employees_count' => 5]);
    switchTo(systemAdmin())->putJson("/api/v1/entities/{$entity->id}", [
        ...profilePayload($entity, ['founding_year' => $entity->founding_year, 'employees_count' => 40, 'authorized_person_name' => ownerOf($entity)->name]),
        'reason' => 'تصحيح عدد الموظفين بناءً على تقرير الجهة السنوي.',
    ])->assertOk();

    switchTo(ownerOf($entity))->patchJson('/api/v1/entities/me/profile/governance', $opened)->assertConflict()->assertJsonPath('code', 'profile.section_stale');

    expect($entity->refresh()->employees_count)->toBe(40);
});

it('does not reject a section whose own values did not change since it was opened', function (): void {
    $entity = entityWithOwner();
    $opened = sectionBody($entity, ProfileSection::Governance, ['employees_count' => 5]);
    saveSection($entity, ProfileSection::Contact, ['marketing_consent' => true])->assertAccepted();
    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertAccepted();

    switchTo(ownerOf($entity))->patchJson('/api/v1/entities/me/profile/governance', $opened)->assertAccepted()->assertJsonPath('data.pending_profile_change.items', ['employees_count', 'logo']);
});

it('rejects invalid section data', function (ProfileSection $section, array $overrides, string $field): void {
    Date::setTestNow('2026-11-04 10:00:00');
    $entity = entityWithOwner();

    saveSection($entity, $section, $overrides)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);

    expect(EntityProfileChange::query()->count())->toBe(0);
})->with([
    'identity without an Arabic name' => [ProfileSection::Identity, ['name' => ['ar' => '', 'en' => 'Al-Amal']], 'name.ar'],
    'identity with a cleared unified number' => [ProfileSection::Identity, ['unified_number' => null], 'unified_number'],
    'identity with an eleven-digit unified number' => [ProfileSection::Identity, ['unified_number' => '60012345678'], 'unified_number'],
    'identity with a founding date in the future' => [ProfileSection::Identity, ['founded_on' => '2026-11-05'], 'founded_on'],
    'classification with an unknown type' => [ProfileSection::Classification, ['entity_type' => 'family_fund'], 'entity_type'],
    'classification without a region' => [ProfileSection::Classification, ['region_id' => null], 'region_id'],
    'governance above 100' => [ProfileSection::Governance, ['governance_grade' => 101], 'governance_grade'],
    'governance with four decimals' => [ProfileSection::Governance, ['governance_grade' => '82.5051'], 'governance_grade'],
    'negative employees' => [ProfileSection::Governance, ['employees_count' => -1], 'employees_count'],
    'strategic plan not a yes or no' => [ProfileSection::Governance, ['has_strategic_plan' => 'sometimes'], 'has_strategic_plan'],
    'contact without the person name' => [ProfileSection::Contact, ['authorized_person_name' => ''], 'authorized_person_name'],
    'contact with a foreign phone' => [ProfileSection::Contact, ['phone' => '+201001234567'], 'phone'],
    'contact without a consent answer' => [ProfileSection::Contact, ['marketing_consent' => null], 'marketing_consent'],
    'contact with an http website' => [ProfileSection::Contact, ['website_url' => 'http://www.alamal.example.sa'], 'website_url'],
    'missing revision' => [ProfileSection::Governance, ['base_revision' => null], 'base_revision'],
]);

it('refuses a city outside the chosen region', function (): void {
    $entity = entityWithOwner();

    saveSection($entity, ProfileSection::Classification, ['city_id' => regionWithCity()->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.city_id.0', __('validation.custom.city_id.not_in_region'));
});

it('requires every field of the section to be sent', function (ProfileSection $section, string $field): void {
    $entity = entityWithOwner();
    $body = sectionBody($entity, $section);
    unset($body[$field]);

    switchTo(ownerOf($entity))->patchJson('/api/v1/entities/me/profile/'.$section->value, $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    [ProfileSection::Identity, 'license_number'],
    [ProfileSection::Identity, 'founded_on'],
    [ProfileSection::Governance, 'governance_grade'],
    [ProfileSection::Governance, 'has_strategic_plan'],
    [ProfileSection::Contact, 'phone'],
    [ProfileSection::Contact, 'website_url'],
]);

it('never changes the official e-mail from the contact section', function (): void {
    $entity = entityWithOwner();
    $email = ownerOf($entity)->email;

    saveSection($entity, ProfileSection::Contact, ['official_email' => 'office@alamal.example.sa'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.official_email.0', __('validation.custom.official_email.prohibited'));

    expect(ownerOf($entity)->email)->toBe($email);
});

it('ignores the fields of other sections', function (): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['entity_type' => 'civil_association']));

    saveAndApproveSection($entity, ProfileSection::Governance, ['employees_count' => 9, 'entity_type' => 'waqf', 'founding_year' => 1950])->assertOk();

    expect($entity->refresh()->employees_count)->toBe(9)
        ->and($entity->entity_type->value)->not->toBe('waqf')
        ->and($entity->founding_year)->not->toBe(1950)
        ->and(array_keys(historyOf($entity->id)))->toBe([EntityProfileField::EmployeesCount->value]);
});
