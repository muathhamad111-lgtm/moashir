<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\ProfileChangeStatus;
use App\Enums\Entities\ProfileSection;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Files\StoredFile;
use App\Notifications\Entities\EntityNotification;
use App\Services\Entities\EntityEraser;
use App\Services\Files\SignedDownloadIssuer;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

const PROFILE_CHANGE_REASON = 'الرقم غير مطابق للسجل الرسمي المعتمد.';

dataset('profile sections', [
    'identity' => [ProfileSection::Identity, ['license_number' => '000777'], 'license_number'],
    'classification' => [ProfileSection::Classification, ['org_size' => 'large'], 'org_size'],
    'governance' => [ProfileSection::Governance, ['employees_count' => 1077], 'employees_count'],
    'contact' => [ProfileSection::Contact, ['website_url' => 'https://amal.org.sa'], 'website_url'],
    'billing' => [ProfileSection::Billing, ['address_street' => 'شارع التحلية'], 'address_street'],
]);

it('puts a section edit into a pending request without touching the approved profile', function (ProfileSection $section, array $edit, string $field): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['org_size' => 'small']));
    $before = $entity->getAttributes();

    saveSection($entity, $section, $edit)
        ->assertAccepted()
        ->assertJsonPath('meta.section', $section->value)
        ->assertJsonPath('data.pending_profile_change.items', [$field])
        ->assertJsonPath('data.pending_profile_change.revision', 1);

    $pending = pendingProfileChangeOf($entity);
    expect($pending?->proposed)->toBe([$field => array_values($edit)[0]])
        ->and($pending?->status)->toBe(ProfileChangeStatus::Pending)
        ->and($entity->refresh()->getAttributes())->toEqual($before)
        ->and(EntityProfileChange::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::EntityProfileChangeSubmitted->value)->count())->toBe(1);
})->with('profile sections');

it('keeps the authorized person and phone on the owner until approval', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    saveSection($entity, ProfileSection::Contact, ['authorized_person_name' => 'نورة بنت سعد القحطاني', 'phone' => '0559876543'])->assertAccepted();

    expect($owner->refresh()->name)->not->toBe('نورة بنت سعد القحطاني')
        ->and(pendingProfileChangeOf($entity)?->proposed)->toMatchArray(['authorized_person_name' => 'نورة بنت سعد القحطاني', 'phone' => '+966559876543']);
});

it('applies marketing consent at once and keeps it out of the request', function (): void {
    $entity = entityWithOwner();

    saveSection($entity, ProfileSection::Contact, ['marketing_consent' => true])
        ->assertAccepted()
        ->assertJsonPath('data.marketing_consent', true)
        ->assertJsonPath('data.pending_profile_change', null);

    expect(pendingProfileChangeOf($entity))->toBeNull()
        ->and($entity->refresh()->marketing_consent_at)->not->toBeNull();
});

it('amends the one pending request across sections and drops fields saved back to their approved value', function (): void {
    $entity = entityWithOwner();
    $approvedSize = $entity->org_size->value;
    $otherSize = $approvedSize === 'large' ? 'small' : 'large';

    saveSection($entity, ProfileSection::Classification, ['org_size' => $otherSize])->assertAccepted();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    saveSection($entity, ProfileSection::Classification, ['org_size' => $approvedSize])
        ->assertAccepted()
        ->assertJsonPath('data.pending_profile_change.items', ['employees_count'])
        ->assertJsonPath('data.pending_profile_change.revision', 3);

    expect(EntityProfileChangeRequest::query()->count())->toBe(1);
});

it('withdraws the request once every proposed change is saved back', function (): void {
    $entity = entityWithOwner();
    $approved = $entity->employees_count;

    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => $approved])
        ->assertAccepted()
        ->assertJsonPath('data.pending_profile_change', null);

    expect(EntityProfileChangeRequest::query()->sole()->status)->toBe(ProfileChangeStatus::Cancelled);
});

it('tells the approvers once when a request opens, not on every amendment', function (): void {
    $approver = systemAdmin();
    $other = staffUser(RoleKey::FinanceOfficer);
    $entity = entityWithOwner();

    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1078])->assertAccepted();

    Notification::assertSentToTimes($approver, EntityNotification::class, 1);
    Notification::assertSentTo($approver, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileChangeRequested);
    Notification::assertNotSentTo($other, EntityNotification::class);
    Notification::assertNotSentTo(ownerOf($entity), EntityNotification::class);
});

it('approves every item, applies them through the profile writer and tells the entity', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077, 'annual_beneficiaries_count' => 91_000])->assertAccepted();
    $request = pendingProfileChangeOf($entity);

    approveProfileChange($entity)
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decisions', ['employees_count' => 'approved', 'annual_beneficiaries_count' => 'approved']);

    expect($entity->refresh()->employees_count)->toBe(1077)
        ->and($entity->annual_beneficiaries_count)->toBe(91_000)
        ->and(EntityProfileChange::query()->pluck('change_request_id')->unique()->all())->toBe([$request?->id])
        ->and(EntityProfileChange::query()->value('changed_by_user_id'))->toBe(ownerOf($entity)->id)
        ->and(Audit::query()->where('event', AuditEvent::EntityProfileChangeDecided->value)->sole()->new_values)
        ->toMatchArray(['status' => 'approved', 'approved' => ['employees_count', 'annual_beneficiaries_count'], 'rejected' => [], 'employees_count' => 1077]);
    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileChangeDecided
        && str_contains($notification->notice->title->ar, 'اعتماد'));
});

it('approves part of a request and rejects the rest with a reason', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Billing, ['address_street' => 'شارع التحلية', 'address_district' => 'حي الملقا'])->assertAccepted();
    $before = $entity->refresh()->address_district;

    decideProfileChange(pendingProfileChangeOf($entity), ['address_street'], PROFILE_CHANGE_REASON)
        ->assertOk()
        ->assertJsonPath('data.status', 'partially_approved')
        ->assertJsonPath('data.review_note', PROFILE_CHANGE_REASON)
        ->assertJsonPath('data.decisions', ['address_street' => 'approved', 'address_district' => 'rejected']);

    expect($entity->refresh()->address_street)->toBe('شارع التحلية')
        ->and($entity->address_district)->toBe($before);
    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification): bool => str_contains($notification->notice->body->ar, 'الحي')
        && str_contains($notification->notice->body->ar, PROFILE_CHANGE_REASON));
});

it('rejects a whole request and keeps the approved profile', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    $approved = $entity->refresh()->employees_count;

    decideProfileChange(pendingProfileChangeOf($entity), [], PROFILE_CHANGE_REASON)->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($entity->refresh()->employees_count)->toBe($approved)
        ->and(EntityProfileChange::query()->count())->toBe(0)
        ->and(pendingProfileChangeOf($entity))->toBeNull();
});

it('requires a reason whenever an item is rejected', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077, 'annual_beneficiaries_count' => 91_000])->assertAccepted();

    decideProfileChange(pendingProfileChangeOf($entity), ['employees_count'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'profile.change_reason_required')
        ->assertJsonValidationErrors('reason');
});

it('refuses to approve a field whose approved value changed, but approves the others', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077, 'annual_beneficiaries_count' => 91_000])->assertAccepted();
    $entity->forceFill(['employees_count' => 1012])->save();
    $request = pendingProfileChangeOf($entity);

    decideProfileChange($request, ['employees_count', 'annual_beneficiaries_count'])
        ->assertConflict()
        ->assertJsonPath('code', 'profile.change_stale')
        ->assertJsonPath('meta.stale_fields', ['employees_count']);

    switchTo(systemAdmin())->getJson('/api/v1/admin/entity-profile-changes/'.$request?->public_id)->assertOk()->assertJsonPath('data.stale_fields', ['employees_count']);

    decideProfileChange($request, ['annual_beneficiaries_count'], PROFILE_CHANGE_REASON)->assertOk()->assertJsonPath('data.status', 'partially_approved');

    expect($entity->refresh()->employees_count)->toBe(1012)->and($entity->annual_beneficiaries_count)->toBe(91_000);
});

it('refuses a decision on a revision the entity amended since', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    $seen = pendingProfileChangeOf($entity);
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1078])->assertAccepted();

    decideProfileChange($seen, ['employees_count'])->assertConflict()->assertJsonPath('code', 'profile.change_amended')->assertJsonPath('meta.revision', 2);

    expect($entity->refresh()->employees_count)->not->toBe(1077);
});

it('refuses to decide a request twice', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    $request = pendingProfileChangeOf($entity);
    decideProfileChange($request, ['employees_count'])->assertOk();

    decideProfileChange($request, [], PROFILE_CHANGE_REASON)->assertConflict();
});

it('refuses items outside the request and splitting coupled fields', function (): void {
    $entity = entityWithOwner();
    $city = regionWithCity();
    saveSection($entity, ProfileSection::Classification, ['region_id' => $city->region_id, 'city_id' => $city->id])->assertAccepted();
    $request = pendingProfileChangeOf($entity);

    decideProfileChange($request, ['website_url'])->assertUnprocessable()->assertJsonPath('code', 'profile.change_unknown_items');
    decideProfileChange($request, ['region_id'], PROFILE_CHANGE_REASON)->assertUnprocessable()->assertJsonPath('code', 'profile.change_coupled_items');
    decideProfileChange($request, ['region_id', 'city_id'])->assertOk();

    expect($entity->refresh()->city_id)->toBe($city->id);
});

it('lets only staff with the approval permission see and decide requests', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    $request = pendingProfileChangeOf($entity);
    $viewer = staffUser(RoleKey::FinanceOfficer);

    switchTo($viewer)->getJson('/api/v1/admin/entity-profile-changes')->assertForbidden();
    switchTo($viewer)->getJson('/api/v1/admin/entity-profile-changes/'.$request?->public_id)->assertForbidden();
    decideProfileChange($request, ['employees_count'], null, $viewer)->assertForbidden();
    decideProfileChange($request, ['employees_count'], null, ownerOf($entity))->assertForbidden();

    $viewer->givePermissionTo(PermissionKey::EntitiesProfileChangeApprove->value);
    decideProfileChange($request, ['employees_count'], null, $viewer->refresh())->assertOk();
});

it('lists the queue with a status filter and an entity search', function (): void {
    $amal = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->named('جمعية الأمل الخيرية'));
    $noor = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->named('مؤسسة سنابل'));
    saveSection($amal, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    saveSection($noor, ProfileSection::Governance, ['employees_count' => 1078])->assertAccepted();
    decideProfileChange(pendingProfileChangeOf($noor), [], PROFILE_CHANGE_REASON)->assertOk();
    $admin = systemAdmin();

    switchTo($admin)->getJson('/api/v1/admin/entity-profile-changes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.entity.id', $amal->id);
    switchTo($admin)->getJson('/api/v1/admin/entity-profile-changes?'.http_build_query(['filter' => ['status' => 'all'], 'search' => 'سنابل']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'rejected');
    $byEntity = switchTo($admin)->getJson('/api/v1/admin/entity-profile-changes?filter[status]=all&filter[entity_id]='.$amal->id)->assertOk();
    expect($byEntity->json('data.*.entity.id'))->toBe([$amal->id]);
    switchTo($admin)->getJson('/api/v1/admin/entity-profile-changes?filter[status]=bogus')->assertUnprocessable();
});

it('shows each item with its approved and proposed value', function (): void {
    $entity = entityWithOwner();
    $city = regionWithCity();
    saveSection($entity, ProfileSection::Classification, ['region_id' => $city->region_id, 'city_id' => $city->id])->assertAccepted();

    $response = switchTo(systemAdmin())->getJson('/api/v1/admin/entity-profile-changes/'.pendingProfileChangeOf($entity)?->public_id)->assertOk();

    expect($response->json('data.changes.0'))->toMatchArray(['item' => 'region_id', 'kind' => 'field', 'section' => 'classification', 'is_stale' => false, 'decision' => null])
        ->and($response->json('data.changes.0.old_value.id'))->toBe($entity->region_id)
        ->and($response->json('data.changes.0.new_value.id'))->toBe($city->region_id)
        ->and($response->json('data.changes.0.label.ar'))->toBe('المنطقة');
});

it('lets the entity read and cancel its own pending request', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();

    switchTo($owner)->getJson('/api/v1/entities/me/profile-change')->assertOk()->assertJsonPath('data.changes.0.new_value', 1077);
    switchTo($owner)->postJson('/api/v1/entities/me/profile-change/cancel')->assertOk()->assertJsonPath('data.pending_profile_change', null);
    switchTo($owner)->getJson('/api/v1/entities/me/profile-change')->assertOk()->assertJsonPath('data', null);
    switchTo($owner)->postJson('/api/v1/entities/me/profile-change/cancel')->assertNotFound()->assertJsonPath('code', 'profile.change_missing');
    switchTo($owner)->getJson('/api/v1/entities/me/profile-change-requests')->assertOk()->assertJsonPath('data.0.status', 'cancelled');

    expect(Audit::query()->where('event', AuditEvent::EntityProfileChangeCancelled->value)->count())->toBe(1);
});

it('keeps an uploaded logo pending and private until it is approved', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    uploadEntityLogo($owner, logoUpload(logoImageBytes()))->assertAccepted()->assertJsonPath('data.logo', null)->assertJsonPath('data.pending_profile_change.items', ['logo']);

    $pending = StoredFile::query()->findOrFail(pendingProfileChangeOf($entity)?->pending_logo_file_id);
    $stranger = ownerOf(entityWithOwner());
    $finance = staffUser(RoleKey::FinanceOfficer);
    expect($entity->refresh()->logo_file_id)->toBeNull()
        ->and($pending->attachable_id)->toBe($entity->id);

    $approver = systemAdmin();

    foreach ([$stranger, $finance] as $viewer) {
        switchTo($viewer)->get(app(SignedDownloadIssuer::class)->issue($pending, $viewer)->url)->assertNotFound();
    }

    switchTo($owner)->get(app(SignedDownloadIssuer::class)->issue($pending, $owner)->url)->assertOk();
    switchTo($approver)->get(app(SignedDownloadIssuer::class)->issue($pending, $approver)->url)->assertOk();

    approveProfileChange($entity)->assertOk();
    expect($entity->refresh()->logo_file_id)->toBe($pending->id);
});

it('replaces a pending logo and removes the superseded file', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    uploadEntityLogo($owner, logoUpload(logoImageBytes()))->assertAccepted();
    $first = pendingProfileChangeOf($entity)?->pending_logo_file_id;

    uploadEntityLogo($owner, logoUpload(logoImageBytes('png', 500, 400)))->assertAccepted();

    expect(StoredFile::query()->find($first))->toBeNull()
        ->and(pendingProfileChangeOf($entity)?->pending_logo_file_id)->not->toBe($first);
});

it('decides the logo and the VAT certificate independently', function (): void {
    $entity = vatRegisteredEntity();
    $owner = ownerOf($entity);
    uploadEntityLogo($owner, logoUpload(logoImageBytes()))->assertAccepted();
    uploadVatCertificate($owner, vatCertificatePdf())->assertAccepted();
    $request = pendingProfileChangeOf($entity);

    decideProfileChange($request, ['logo'], PROFILE_CHANGE_REASON)->assertOk()->assertJsonPath('data.decisions', ['logo' => 'approved', 'vat_certificate' => 'rejected']);

    expect($entity->refresh()->logo_file_id)->toBe($request?->pending_logo_file_id)
        ->and($entity->vat_certificate_file_id)->toBeNull()
        ->and(StoredFile::query()->find($request?->pending_vat_certificate_file_id))->toBeNull();
});

it('accepts a VAT certificate only for an entity registered or proposing registration', function (): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['vat_registered' => false, 'vat_number' => null]));
    $owner = ownerOf($entity);

    uploadVatCertificate($owner, vatCertificatePdf())->assertConflict()->assertJsonPath('code', 'entity.vat_not_registered');

    saveSection($entity, ProfileSection::Billing, ['vat_registered' => true, 'vat_number' => '310123456700003'])->assertAccepted();
    uploadVatCertificate($owner, vatCertificatePdf())->assertAccepted();

    $request = pendingProfileChangeOf($entity);
    decideProfileChange($request, ['vat_certificate'], PROFILE_CHANGE_REASON)->assertConflict()->assertJsonPath('code', 'entity.vat_not_registered');
    decideProfileChange($request, ['vat_registered', 'vat_number', 'vat_certificate'])->assertOk();

    expect($entity->refresh()->vat_certificate_file_id)->toBe($request?->pending_vat_certificate_file_id);
});

it('proposes removing an approved logo and ignores a removal when there is none', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    switchTo($owner)->deleteJson('/api/v1/entities/me/logo')->assertAccepted()->assertJsonPath('data.pending_profile_change', null);

    uploadEntityLogo($owner, logoUpload(logoImageBytes()))->assertAccepted();
    approveProfileChange($entity)->assertOk();
    switchTo($owner)->deleteJson('/api/v1/entities/me/logo')->assertAccepted()->assertJsonPath('data.pending_profile_change.items', ['logo']);

    expect(pendingProfileChangeOf($entity)?->removes_logo)->toBeTrue()->and($entity->refresh()->logo_file_id)->not->toBeNull();

    approveProfileChange($entity)->assertOk();
    expect($entity->refresh()->logo_file_id)->toBeNull();
});

it('refuses to approve a file that has not passed its security scan', function (): void {
    $entity = entityWithOwner();
    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertAccepted();
    $request = pendingProfileChangeOf($entity);
    StoredFile::query()->whereKey($request?->pending_logo_file_id)->update(['malware_scan_status' => 'pending']);

    decideProfileChange($request, ['logo'])->assertConflict()->assertJsonPath('code', 'profile.change_file_not_clean');
});

it('counts pending requests in the action center of their approvers', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();

    expect(actionItems(systemAdmin())->get('review_entity_profile_changes'))->toMatchArray(['count' => 1, 'link' => '/admin/entities/profile-changes'])
        ->and(actionItems(staffUser(RoleKey::FinanceOfficer))->all())->toBe([]);
});

it('erases pending requests and their files with the entity', function (): void {
    $entity = entityWithOwner();
    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertAccepted();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 1077])->assertAccepted();
    $fileId = pendingProfileChangeOf($entity)?->pending_logo_file_id;
    $entity->delete();

    app(EntityEraser::class)->erase($entity->refresh());

    expect(EntityProfileChangeRequest::query()->count())->toBe(0)
        ->and(StoredFile::query()->find($fileId))->toBeNull();
});
