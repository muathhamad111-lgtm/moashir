<?php

declare(strict_types=1);

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\PermissionModule;
use App\Enums\Identity\RoleKey;
use App\Services\Identity\DefaultPermissionMatrix;

it('defines 83 unique permission keys in the module.resource.action format', function (): void {
    expect(PermissionKey::values())->toHaveCount(83)
        ->and(array_unique(PermissionKey::values()))->toHaveCount(83);

    foreach (PermissionKey::cases() as $key) {
        expect($key->value)->toMatch('/^[a-z_]+(\.[a-z_]+){1,3}$/');
    }
});

it('places the keys in their catalog modules', function (PermissionKey $key, PermissionModule $module): void {
    expect($key->module())->toBe($module);
})->with([
    [PermissionKey::NotificationsView, PermissionModule::Dashboard],
    [PermissionKey::EntitiesDelete, PermissionModule::Entity],
    [PermissionKey::PrivacyRequestsManage, PermissionModule::Privacy],
    [PermissionKey::AssessmentScoreManage, PermissionModule::Requests],
    [PermissionKey::AssessmentSelfManage, PermissionModule::Assessment],
    [PermissionKey::CertificatesManage, PermissionModule::Reports],
    [PermissionKey::MessagingCampaignsSend, PermissionModule::Settings],
    [PermissionKey::AuditExport, PermissionModule::Admin],
    [PermissionKey::PaymentsOwnCreate, PermissionModule::Finance],
]);

it('marks exactly the ten independent grantable keys', function (): void {
    $grantable = array_values(array_filter(PermissionKey::cases(), static fn (PermissionKey $key): bool => $key->isGrantable()));

    expect($grantable)->toHaveCount(10)
        ->and($grantable)->toContain(PermissionKey::BagPlanApprove, PermissionKey::EntitiesDelete, PermissionKey::DashboardExecutiveView);
});

it('hard-locks the branding permission to the system administrator', function (): void {
    expect(PermissionKey::SettingsBrandingManage->lockedToRoles())->toBe([RoleKey::SystemAdmin])
        ->and(PermissionKey::SettingsGeneralManage->isLocked())->toBeFalse();
});

it('marks the secret-bearing settings as confidential', function (): void {
    expect(PermissionKey::SettingsPaymentManage->isConfidential())->toBeTrue()
        ->and(PermissionKey::AiProviderManage->isConfidential())->toBeTrue()
        ->and(PermissionKey::SettingsGeneralManage->isConfidential())->toBeFalse();
});

it('seeds the matrix of analysis-permissions section 3', function (): void {
    $matrix = new DefaultPermissionMatrix;

    expect($matrix->for(RoleKey::SystemAdmin))->toContain(PermissionKey::SettingsBrandingManage, PermissionKey::AuditView, PermissionKey::AiProviderManage)
        ->not->toContain(PermissionKey::AssessmentScoreManage, PermissionKey::AwardSecretaryDecide, PermissionKey::FinanceReceiptsManage)
        ->and($matrix->for(RoleKey::FinanceOfficer))->not->toContain(PermissionKey::AuditView)
        ->and($matrix->for(RoleKey::ContentOfficer))->toContain(PermissionKey::AiKbManage)->not->toContain(PermissionKey::AiProviderManage)
        ->and($matrix->for(RoleKey::Assessor))->toContain(PermissionKey::AwardCommitteeScore)->not->toContain(PermissionKey::RequestsQueueView)
        ->and($matrix->for(RoleKey::Entity))->toContain(PermissionKey::AssessmentSelfManage)->not->toContain(PermissionKey::EntitiesView);
});

it('never seeds a locked key outside its role', function (): void {
    $matrix = new DefaultPermissionMatrix;

    foreach (RoleKey::cases() as $role) {
        foreach ($matrix->for($role) as $key) {
            expect(! $key->isLocked() || in_array($role, $key->lockedToRoles(), true))->toBeTrue();
        }
    }
});

it('gives every role the dashboard and own notifications without duplicates', function (): void {
    $matrix = new DefaultPermissionMatrix;

    foreach (RoleKey::cases() as $role) {
        $keys = array_map(static fn (PermissionKey $key): string => $key->value, $matrix->for($role));

        expect($keys)->toContain('dashboard.view', 'notifications.view')
            ->and(array_unique($keys))->toHaveCount(count($keys));
    }
});

it('marks only the entity role as not assignable and the award roles as cycle scoped', function (): void {
    expect(RoleKey::assignableValues())->not->toContain('entity')->toHaveCount(8)
        ->and(RoleKey::FilteringOfficer->isCycleScoped())->toBeTrue()
        ->and(RoleKey::Assessor->isCycleScoped())->toBeFalse()
        ->and(RoleKey::values())->not->toContain('super_admin');
});
