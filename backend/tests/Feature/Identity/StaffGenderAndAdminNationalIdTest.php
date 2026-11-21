<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\Gender;
use App\Enums\Identity\IdentityChangeSource;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Models\Identity\UserIdentityChange;
use Illuminate\Support\Facades\Notification;

const OWN_GENDER = '/api/v1/me/gender';

beforeEach(function (): void {
    Notification::fake();
});

function invitedUser(string $email = 'new.staff@moashir.example.sa'): User
{
    return User::query()->where('email', $email)->sole();
}

function auditTrailOf(User $user): string
{
    return Audit::query()->where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)->get()->toJson();
}

it('records the gender of an invited staff member and shows it on the staff record', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/users', ['name' => 'ريم الحربي', 'gender' => 'female', 'email' => 'new.staff@moashir.example.sa', 'roles' => ['assessor']])
        ->assertCreated()
        ->assertJsonPath('data.gender', 'female')
        ->assertJsonPath('data.national_id_masked', null);

    expect(invitedUser()->gender)->toBe(Gender::Female)
        ->and(auditTrailOf(invitedUser()))->not->toContain('female');
});

it('saves a national ID the administrator enters at invite directly, records and audits it', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);

    $this->postJson('/api/v1/users', ['name' => 'ريم الحربي', 'gender' => 'female', 'email' => 'new.staff@moashir.example.sa', 'roles' => ['assessor'], 'national_id' => '١٠٠٠٠٠٠٥٠٥'])
        ->assertCreated()
        ->assertJsonPath('data.national_id_masked', '••••••0505');

    $invitee = invitedUser();
    $history = UserIdentityChange::query()->sole();
    expect($invitee->national_id)->toBe('1000000505')
        ->and(IdentityChangeRequest::query()->count())->toBe(0)
        ->and($history->source)->toBe(IdentityChangeSource::AdminEntry)
        ->and($history->approved_by_user_id)->toBe($administrator->id);
    expect(Audit::query()->where('event', AuditEvent::UserNationalIdRecorded->value)->sole()->new_values)->toBe(['source' => 'admin_entry'])
        ->and(Audit::query()->get()->toJson())->not->toContain('1000000505');
});

it('refuses at invite a national ID already held by another account', function (): void {
    giveNationalId(staffUser(RoleKey::Assessor), 515);
    signIn(systemAdmin());

    $this->postJson('/api/v1/users', ['name' => 'ريم الحربي', 'gender' => 'female', 'email' => 'new.staff@moashir.example.sa', 'roles' => ['assessor'], 'national_id' => fictionalNationalId(515)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'identity.national_id_unavailable');

    expect(User::query()->where('email', 'new.staff@moashir.example.sa')->exists())->toBeFalse();
});

it('lets the administrator change the gender on the edit form and audits it without the value', function (): void {
    $target = staffUser(RoleKey::Assessor);
    signIn(systemAdmin());

    $this->putJson("/api/v1/users/{$target->id}", ['gender' => 'male'])->assertOk()->assertJsonPath('data.gender', 'male');
    $this->putJson("/api/v1/users/{$target->id}", ['gender' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('gender');
    $this->putJson("/api/v1/users/{$target->id}", ['gender' => ''])->assertUnprocessable()->assertJsonValidationErrors('gender');

    expect($target->refresh()->gender)->toBe(Gender::Male)
        ->and(Audit::query()->where('event', AuditEvent::UserGenderRecorded->value)->sole()->new_values)->toBe(['by' => 'administrator'])
        ->and(auditTrailOf($target))->not->toContain('"male"');
});

it('lets the administrator set a national ID on the edit form only while none is recorded', function (): void {
    $target = staffUser(RoleKey::Assessor);
    signIn(systemAdmin());

    $this->putJson("/api/v1/users/{$target->id}", ['national_id' => fictionalNationalId(525)])
        ->assertOk()
        ->assertJsonPath('data.national_id_masked', '••••••'.substr(fictionalNationalId(525), -4));
    $this->putJson("/api/v1/users/{$target->id}", ['national_id' => fictionalNationalId(526)])
        ->assertConflict()
        ->assertJsonPath('code', 'identity.national_id_locked');

    expect($target->refresh()->national_id)->toBe(fictionalNationalId(525));
});

it('refuses the user administration fields to accounts without user management', function (): void {
    $target = staffUser(RoleKey::Assessor);
    signIn(staffUser(RoleKey::FinanceOfficer));

    $this->putJson("/api/v1/users/{$target->id}", ['gender' => 'male', 'national_id' => fictionalNationalId(535)])->assertForbidden();

    expect($target->refresh()->gender)->toBeNull()
        ->and($target->national_id)->toBeNull();
});

it('reports a staff account without a gender in the session, the Super Admin included', function (): void {
    signIn(staffUser(RoleKey::Assessor));
    $this->getJson('/api/v1/auth/session')->assertJsonPath('data.gender_missing', true);

    switchTo(superAdmin())->getJson('/api/v1/auth/session')->assertJsonPath('data.gender_missing', true);
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/auth/session')->assertJsonPath('data.gender_missing', false);
});

it('lets a staff account complete its own gender and audits it without the value', function (): void {
    $assessor = staffUser(RoleKey::Assessor);
    signIn($assessor);

    $this->patchJson(OWN_GENDER, ['gender' => 'female'])->assertNoContent();

    expect($assessor->refresh()->gender)->toBe(Gender::Female)
        ->and(Audit::query()->where('event', AuditEvent::UserGenderRecorded->value)->sole()->new_values)->toBe(['by' => 'self'])
        ->and(auditTrailOf($assessor))->not->toContain('female');
    $this->getJson('/api/v1/auth/session')->assertJsonPath('data.gender_missing', false);
});

it('validates the own gender and keeps it to staff accounts', function (): void {
    signIn(staffUser(RoleKey::Assessor));
    $this->patchJson(OWN_GENDER, ['gender' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('gender');
    $this->patchJson(OWN_GENDER, [])->assertUnprocessable()->assertJsonValidationErrors('gender');

    $owner = ownerOf(entityWithOwner());
    switchTo($owner)->patchJson(OWN_GENDER, ['gender' => 'male'])->assertForbidden();
    expect($owner->refresh()->gender)->toBeNull();
});

it('refuses the own gender to guests', function (): void {
    $this->patchJson(OWN_GENDER, ['gender' => 'male'])->assertUnauthorized();
});
