<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
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

it('stops a required account without a national ID until it adds one', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    $assessor = staffUser(RoleKey::Assessor);
    signIn($assessor);

    $this->getJson(GATED_ROUTE)->assertForbidden()->assertJsonPath('code', 'identity.national_id_required');
    $this->getJson('/api/v1/auth/session')->assertOk()
        ->assertJsonPath('data.national_id_required', true)
        ->assertJsonPath('data.has_national_id', false)
        ->assertJsonPath('data.national_id_masked', null);
    $this->patchJson('/api/v1/me/preferences', ['locale' => 'en'])->assertOk();

    $number = fictionalNationalId(101);
    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $number])
        ->assertCreated()
        ->assertJsonPath('data.national_id_masked', '••••••'.substr($number, -4));

    $this->getJson(GATED_ROUTE)->assertOk();
    $this->getJson('/api/v1/auth/session')->assertJsonPath('data.has_national_id', true);
});

it('never stops accounts whose roles are not required', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    signIn(staffUser(RoleKey::CriteriaOfficer));

    $this->getJson(GATED_ROUTE)->assertOk();
});

it('never stops anyone while the requirement is off', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    app(SettingsStore::class)->put(SettingKey::NationalIdEnabled, false, AuditEvent::AuthSettingsUpdated, null);
    signIn(staffUser(RoleKey::Assessor));

    $this->getJson(GATED_ROUTE)->assertOk();
});

it('always requires the Super Admin while the requirement is on', function (): void {
    requireNationalIdFor(RoleKey::Entity);
    signIn(superAdmin());

    $this->getJson(GATED_ROUTE)->assertForbidden()->assertJsonPath('code', 'identity.national_id_required');
});

it('applies a newly required role on the next request', function (): void {
    $assessor = staffUser(RoleKey::Assessor);
    signIn($assessor);
    $this->getJson(GATED_ROUTE)->assertOk();

    requireNationalIdFor(RoleKey::Assessor);

    $this->getJson(GATED_ROUTE)->assertForbidden();
});

it('stores the national ID encrypted with a blind index and audits it without the value', function (): void {
    $user = staffUser(RoleKey::Assessor);
    $number = fictionalNationalId(202);
    signIn($user);

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $number])->assertCreated();

    $row = DB::table('users')->where('id', $user->id)->first(['national_id', 'national_id_index']);
    expect($row->national_id)->not->toContain($number)
        ->and($row->national_id_index)->toHaveLength(64)
        ->and($user->refresh()->national_id)->toBe($number);
    $audit = Audit::query()->where('event', AuditEvent::UserNationalIdRecorded->value)->sole();
    expect(json_encode([$audit->old_values, $audit->new_values]))->not->toContain($number);
});

it('locks the national ID after the first entry', function (): void {
    $user = giveNationalId(staffUser(RoleKey::Assessor), 303);
    signIn($user);

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => fictionalNationalId(304)])
        ->assertConflict()
        ->assertJsonPath('code', 'identity.national_id_locked');
    expect($user->refresh()->national_id)->toBe(fictionalNationalId(303));
});

it('refuses an invalid number without echoing it', function (string $input): void {
    signIn(staffUser(RoleKey::Assessor));

    $response = $this->postJson(OWN_NATIONAL_ID, ['national_id' => $input], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['national_id' => 'Enter a valid 10-digit national ID or Iqama number.']);

    expect($response->getContent())->not->toContain($input);
})->with(['bad check digit' => '1000000009', 'wrong length' => '12345', 'starts with 5' => '5000000001']);

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

    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $number])->assertCreated();
    $this->postJson(OWN_NATIONAL_ID, ['national_id' => $number])->assertConflict();

    Log::shouldNotHaveReceived('log', fn (...$arguments): bool => str_contains((string) json_encode($arguments), $number));
    foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
        Log::shouldNotHaveReceived($level, fn (...$arguments): bool => str_contains((string) json_encode($arguments), $number));
    }
    expect(Audit::query()->get()->toJson())->not->toContain($number);
});
