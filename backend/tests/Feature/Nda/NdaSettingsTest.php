<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Identity\TermsAcceptance;
use App\Services\Settings\SettingsStore;

it('shows the agreement settings with the six default roles and the three-day delay', function (): void {
    signIn(systemAdmin())->getJson('/api/v1/settings/nda')
        ->assertOk()
        ->assertJsonPath('data.roles', ['system_admin', 'criteria_officer', 'request_follower', 'assessor', 'filtering_officer', 'award_secretary'])
        ->assertJsonPath('data.reminder_days', 3)
        ->assertJsonPath('data.current', null);
});

it('changes the roles in scope and the delay with a reason, through the audited settings', function (): void {
    signIn(systemAdmin())->putJson('/api/v1/settings/nda', [
        'roles' => ['assessor', 'finance_officer'],
        'reminder_days' => 5,
        'reason' => 'ضم المسؤولين الماليين إلى نطاق الاتفاقية.',
    ])->assertOk()->assertJsonPath('data.roles', ['assessor', 'finance_officer'])->assertJsonPath('data.reminder_days', 5);

    $scope = Audit::query()->where('event', AuditEvent::NdaScopeChanged->value)->sole();
    expect(app(SettingsStore::class)->get(SettingKey::NdaRoles))->toBe(['assessor', 'finance_officer'])
        ->and($scope->reason)->toBe('ضم المسؤولين الماليين إلى نطاق الاتفاقية.')
        ->and(Audit::query()->where('event', AuditEvent::NdaReminderDelayChanged->value)->count())->toBe(1);
});

it('validates the settings change', function (array $payload, string $field): void {
    signIn(systemAdmin())->putJson('/api/v1/settings/nda', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no reason' => [['roles' => ['assessor']], 'reason'],
    'entity role' => [['roles' => ['entity'], 'reason' => 'سبب كافٍ للتغيير هنا.'], 'roles.0'],
    'unknown role' => [['roles' => ['auditor'], 'reason' => 'سبب كافٍ للتغيير هنا.'], 'roles.0'],
    'delay too long' => [['reminder_days' => 90, 'reason' => 'سبب كافٍ للتغيير هنا.'], 'reminder_days'],
    'nothing to change' => [['reason' => 'سبب كافٍ للتغيير هنا.'], 'roles'],
]);

it('keeps every acceptance when a role leaves the scope', function (): void {
    $version = publishedNda();
    $assessor = staffUser(RoleKey::Assessor);
    acceptNdaAs($assessor, $version);
    $admin = systemAdmin();
    acceptNdaAs($admin, $version);

    signIn($admin)->putJson('/api/v1/settings/nda', ['roles' => ['system_admin'], 'reason' => 'إخراج المقيّمين من النطاق مؤقتًا.'])->assertOk();

    expect(TermsAcceptance::query()->where('user_id', $assessor->id)->count())->toBe(1);
});

it('refuses the settings change to staff without nda.manage', function (): void {
    signIn(staffUser(RoleKey::RequestFollower))->putJson('/api/v1/settings/nda', ['roles' => [], 'reason' => 'سبب كافٍ للتغيير هنا.'])->assertForbidden();
});
