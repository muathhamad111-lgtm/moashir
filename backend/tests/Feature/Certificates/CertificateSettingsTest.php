<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;

const CERTIFICATE_SETTINGS = '/api/v1/settings/certificates';

beforeEach(function (): void {
    Notification::fake();
});

it('shows the default level thresholds and issuance switch to the System Administrator', function (): void {
    switchTo(systemAdmin())->getJson(CERTIFICATE_SETTINGS)->assertOk()
        ->assertJsonPath('data.accredited_issuance_enabled', true)
        ->assertJsonPath('data.level_thresholds', ['bronze' => '60.00', 'silver' => '70.00', 'gold' => '80.00', 'diamond' => '90.00']);
});

it('updates the thresholds and the issuance switch and audits every changed key', function (): void {
    switchTo(systemAdmin())->putJson(CERTIFICATE_SETTINGS, [
        'level_thresholds' => ['bronze' => '55', 'silver' => '65.5', 'gold' => '78.25', 'diamond' => '92.00'],
        'accredited_issuance_enabled' => false,
    ])->assertOk()
        ->assertJsonPath('data.accredited_issuance_enabled', false)
        ->assertJsonPath('data.level_thresholds', ['bronze' => '55.00', 'silver' => '65.50', 'gold' => '78.25', 'diamond' => '92.00']);

    expect(Audit::query()->where('event', AuditEvent::CertificateSettingsUpdated->value)->count())->toBe(2);
});

it('refuses thresholds that are missing, malformed or not ascending', function (array $payload, string $field): void {
    switchTo(systemAdmin())->putJson(CERTIFICATE_SETTINGS, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Audit::query()->where('event', AuditEvent::CertificateSettingsUpdated->value)->count())->toBe(0);
})->with([
    'missing level' => [['level_thresholds' => ['bronze' => '60', 'silver' => '70', 'gold' => '80']], 'level_thresholds.diamond'],
    'three decimals' => [['level_thresholds' => ['bronze' => '60.125', 'silver' => '70', 'gold' => '80', 'diamond' => '90']], 'level_thresholds.bronze'],
    'above 100' => [['level_thresholds' => ['bronze' => '60', 'silver' => '70', 'gold' => '80', 'diamond' => '100.01']], 'level_thresholds.diamond'],
    'zero' => [['level_thresholds' => ['bronze' => '0', 'silver' => '70', 'gold' => '80', 'diamond' => '90']], 'level_thresholds.bronze'],
    'not ascending' => [['level_thresholds' => ['bronze' => '75', 'silver' => '70', 'gold' => '80', 'diamond' => '90']], 'level_thresholds'],
    'unknown level' => [['level_thresholds' => ['bronze' => '60', 'silver' => '70', 'gold' => '80', 'diamond' => '90', 'platinum' => '95']], 'level_thresholds'],
    'switch not boolean' => [['accredited_issuance_enabled' => 'sometimes'], 'accredited_issuance_enabled'],
]);

it('keeps the certificate settings to holders of the certificates settings permission', function (RoleKey $role): void {
    switchTo(staffUser($role))->getJson(CERTIFICATE_SETTINGS)->assertForbidden();
    switchTo(staffUser($role))->putJson(CERTIFICATE_SETTINGS, ['accredited_issuance_enabled' => false])->assertForbidden();
})->with([RoleKey::Assessor, RoleKey::RequestFollower, RoleKey::FinanceOfficer, RoleKey::AwardSecretary]);

it('asks the Super Admin for a reason and raises the alert', function (): void {
    $superAdmin = superAdmin();

    switchTo($superAdmin)->putJson(CERTIFICATE_SETTINGS, ['accredited_issuance_enabled' => false])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    switchTo($superAdmin)->putJson(CERTIFICATE_SETTINGS, ['accredited_issuance_enabled' => false, 'reason' => CERTIFICATE_REASON])->assertOk();

    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});
