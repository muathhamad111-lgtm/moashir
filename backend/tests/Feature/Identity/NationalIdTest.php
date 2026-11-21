<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\IdentityChangeKind;
use App\Enums\Identity\IdentityChangeSource;
use App\Enums\Identity\IdentityChangeStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Models\Identity\UserIdentityChange;
use App\Notifications\Entities\EntityNotification;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

const NATIONAL_ID_SETTINGS_URL = '/api/v1/settings/auth';

const OWN_NATIONAL_ID = '/api/v1/me/national-id';

const GATED_ROUTE = '/api/v1/me/sessions';

beforeEach(function (): void {
    Notification::fake();
});

it('ships with the national ID requirement off and lists the roles that open files', function (): void {
    signIn(systemAdmin());

    $this->getJson(NATIONAL_ID_SETTINGS_URL)
        ->assertOk()
        ->assertJsonPath('data.national_id_enabled', false)
        ->assertJsonPath('data.national_id_roles', [])
        ->assertJsonPath('data.file_access_roles', fn (array $roles): bool => in_array('assessor', $roles, true)
            && in_array('entity', $roles, true)
            && ! in_array('criteria_officer', $roles, true)
            && ! in_array('content_officer', $roles, true));
});

it('lets the System Administrator choose the roles that must provide a national ID and audits it', function (): void {
    signIn(systemAdmin());

    $this->putJson(NATIONAL_ID_SETTINGS_URL, ['national_id_enabled' => true, 'national_id_roles' => ['assessor', 'entity']])
        ->assertOk()
        ->assertJsonPath('data.national_id_enabled', true)
        ->assertJsonPath('data.national_id_roles', ['assessor', 'entity']);

    $audits = Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->get();
    expect($audits->pluck('new_values')->all())->toContain(
        [SettingKey::NationalIdEnabled->value => true],
        [SettingKey::NationalIdRoles->value => ['assessor', 'entity']],
    );
});

it('refuses the national ID settings to other roles', function (): void {
    signIn(staffUser(RoleKey::Assessor));

    $this->putJson(NATIONAL_ID_SETTINGS_URL, ['national_id_enabled' => true])->assertForbidden();
});

it('refuses unknown role keys in the national ID settings', function (): void {
    signIn(systemAdmin());

    $this->putJson(NATIONAL_ID_SETTINGS_URL, ['national_id_roles' => ['captain']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('national_id_roles.0');
});

it('never blocks a required account without a national ID and reports it as missing in the session', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    signIn(staffUser(RoleKey::Assessor));

    $this->getJson(GATED_ROUTE)->assertOk();
    $this->getJson('/api/v1/auth/session')->assertOk()
        ->assertJsonPath('data.national_id_required', true)
        ->assertJsonPath('data.national_id_status', 'missing')
        ->assertJsonPath('data.has_national_id', false)
        ->assertJsonPath('data.national_id_masked', null);
});

it('reports the national ID as not required for other roles and while the requirement is off', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    signIn(staffUser(RoleKey::CriteriaOfficer));
    $this->getJson('/api/v1/auth/session')->assertJsonPath('data.national_id_status', 'not_required');

    app(SettingsStore::class)->put(SettingKey::NationalIdEnabled, false, AuditEvent::AuthSettingsUpdated, null);
    switchTo(staffUser(RoleKey::Assessor))->getJson('/api/v1/auth/session')->assertJsonPath('data.national_id_status', 'not_required');
});

it('always counts the Super Admin as required while the requirement is on, without blocking', function (): void {
    requireNationalIdFor(RoleKey::Entity);
    signIn(superAdmin());

    $this->getJson(GATED_ROUTE)->assertOk();
    $this->getJson('/api/v1/auth/session')->assertJsonPath('data.national_id_status', 'missing');
});

it('sends a staff member\'s own first number for approval and reports it pending', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    $assessor = staffUser(RoleKey::Assessor);
    $reviewer = systemAdmin();
    signIn($assessor);

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(101)])
        ->assertAccepted()
        ->assertJsonPath('data.national_id_status', 'pending')
        ->assertJsonPath('data.national_id_masked', null);

    $request = IdentityChangeRequest::query()->sole();
    expect($assessor->refresh()->national_id)->toBeNull()
        ->and($request->kind)->toBe(IdentityChangeKind::FirstEntry)
        ->and($request->status)->toBe(IdentityChangeStatus::Pending)
        ->and($request->reason)->toBeNull()
        ->and($request->new_national_id)->toBe(fictionalNationalId(101));
    $this->getJson('/api/v1/auth/session')->assertJsonPath('data.national_id_status', 'pending');
    Notification::assertSentTo($reviewer, EntityNotification::class, fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::IdentityChangeRequested);
});

it('records an approved first entry in the identity history and reports it provided', function (): void {
    $assessor = staffUser(RoleKey::Assessor);
    $reviewer = systemAdmin();
    switchTo($assessor)->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(111)])->assertAccepted();
    $request = IdentityChangeRequest::query()->sole();

    switchTo($reviewer)->postJson("/api/v1/identity-changes/{$request->public_id}/approve")->assertOk();

    expect($assessor->refresh()->national_id)->toBe(fictionalNationalId(111));
    $history = UserIdentityChange::query()->sole();
    expect($history->source)->toBe(IdentityChangeSource::ApprovedRequest)
        ->and($history->old_value)->toBeNull()
        ->and($history->approved_by_user_id)->toBe($reviewer->id);
    switchTo($assessor)->getJson('/api/v1/auth/session')
        ->assertJsonPath('data.national_id_status', 'provided')
        ->assertJsonPath('data.national_id_masked', '••••••'.substr(fictionalNationalId(111), -4));
});

it('returns the account to missing when its first entry is rejected', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    $assessor = staffUser(RoleKey::Assessor);
    switchTo($assessor)->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(121)])->assertAccepted();
    $request = IdentityChangeRequest::query()->sole();

    switchTo(systemAdmin())->postJson("/api/v1/identity-changes/{$request->public_id}/reject", ['note' => 'The number does not match the employee file.'])->assertOk();

    expect($assessor->refresh()->national_id)->toBeNull();
    switchTo($assessor)->getJson('/api/v1/auth/session')->assertJsonPath('data.national_id_status', 'missing');
});

it('sends an entity representative\'s own number for approval too', function (): void {
    requireNationalIdFor(RoleKey::Entity);
    $owner = ownerOf(entityWithOwner());
    signIn($owner);

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(131)])->assertAccepted()->assertJsonPath('data.national_id_status', 'pending');

    expect($owner->refresh()->national_id)->toBeNull()
        ->and(IdentityChangeRequest::query()->sole()->kind)->toBe(IdentityChangeKind::FirstEntry);
});

it('saves the Super Admin\'s own number directly, encrypted with a blind index, and audits it without the value', function (): void {
    $superAdmin = superAdmin();
    $number = fictionalNationalId(202);
    signIn($superAdmin);

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $number])
        ->assertCreated()
        ->assertJsonPath('data.national_id_status', 'provided')
        ->assertJsonPath('data.national_id_masked', '••••••'.substr($number, -4));

    $row = DB::table('users')->where('id', $superAdmin->id)->first(['national_id', 'national_id_index']);
    expect($row->national_id)->not->toContain($number)
        ->and($row->national_id_index)->toHaveLength(64)
        ->and($superAdmin->refresh()->national_id)->toBe($number)
        ->and(IdentityChangeRequest::query()->count())->toBe(0)
        ->and(UserIdentityChange::query()->sole()->source)->toBe(IdentityChangeSource::SuperAdminEntry);
    $audit = Audit::query()->where('event', AuditEvent::UserNationalIdRecorded->value)->sole();
    expect($audit->new_values)->toBe(['source' => 'super_admin_entry'])
        ->and(json_encode([$audit->old_values, $audit->new_values]))->not->toContain($number);
});

it('refuses a second first entry while one is pending', function (): void {
    signIn(staffUser(RoleKey::Assessor));
    $this->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(141)])->assertAccepted();

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(142)])
        ->assertConflict()
        ->assertJsonPath('code', 'identity_change.already_pending');
});

it('locks the national ID after it is recorded', function (): void {
    $user = giveNationalId(staffUser(RoleKey::Assessor), 303);
    signIn($user);

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(304)])
        ->assertConflict()
        ->assertJsonPath('code', 'identity.national_id_locked');
    expect($user->refresh()->national_id)->toBe(fictionalNationalId(303))
        ->and(IdentityChangeRequest::query()->count())->toBe(0);
});

it('says which rule an invalid number breaks, in Arabic and English, without echoing it', function (string $input, string $rule): void {
    signIn(staffUser(RoleKey::Assessor));

    foreach (['ar', 'en'] as $locale) {
        $response = $this->postJson(OWN_NATIONAL_ID, ['national_id' => $input], ['Accept-Language' => $locale])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['national_id' => __('validation.custom.national_id.'.$rule, [], $locale)]);

        expect($response->getContent())->not->toContain($input);
    }
})->with([
    'a letter' => ['10000a0008', 'digits'],
    'a symbol' => ['1000-00008', 'digits'],
    'nine digits' => ['100000000', 'length'],
    'eleven digits' => ['10000000081', 'length'],
    'starts with 3' => ['3000000008', 'prefix'],
]);

it('accepts Arabic-Indic digits and numbers the old check digit refused', function (string $input): void {
    signIn(staffUser(RoleKey::Assessor));

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $input])->assertAccepted();

    expect(IdentityChangeRequest::query()->sole()->new_national_id)->toBe('1000000009');
})->with(['Western digits' => '1000000009', 'Arabic-Indic digits' => '١٠٠٠٠٠٠٠٠٩']);

it('refuses a number already held by another account with a generic message', function (): void {
    giveNationalId(staffUser(RoleKey::Assessor), 404);
    signIn(staffUser(RoleKey::Assessor));

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(404)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'identity.national_id_unavailable');
});

it('requires the national ID at entity registration only when entities are required', function (): void {
    $this->getJson('/api/v1/auth/register/options')->assertOk()->assertJsonPath('data.national_id_required', false);
    $this->postJson('/api/v1/auth/register', registrationPayload(['national_id' => fictionalNationalId(505)]))->assertCreated();
    expect(User::query()->where('email', 'info@alamal.example.sa')->sole()->national_id)->toBeNull();
});

it('records the national ID of the registering person when entities are required', function (): void {
    requireNationalIdFor(RoleKey::Entity);
    $this->getJson('/api/v1/auth/register/options')->assertJsonPath('data.national_id_required', true);

    $this->postJson('/api/v1/auth/register', registrationPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('national_id');

    $this->postJson('/api/v1/auth/register', registrationPayload(['national_id' => fictionalNationalId(606)]))->assertCreated();
    expect(User::query()->where('email', 'info@alamal.example.sa')->sole()->national_id)->toBe(fictionalNationalId(606));
});

it('never writes a national ID into the logs or the audit trail', function (): void {
    Log::spy();
    requireNationalIdFor(RoleKey::Assessor);
    $number = fictionalNationalId(707);
    signIn(staffUser(RoleKey::Assessor));

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $number])->assertAccepted();
    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $number])->assertConflict();
    switchTo(superAdmin())->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(708)])->assertCreated();

    foreach (['log', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
        Log::shouldNotHaveReceived($level, fn (...$arguments): bool => str_contains((string) json_encode($arguments), $number));
    }
    expect(Audit::query()->get()->toJson())->not->toContain($number)->not->toContain(fictionalNationalId(708));
});
