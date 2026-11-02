<?php

declare(strict_types=1);

use App\Data\Settings\FinanceSettingsUpdate;
use App\Enums\Identity\PermissionKey;
use App\Enums\Settings\FinanceSettingField;
use App\Enums\Settings\SettingKey;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\VatRate;

it('lists only the settings present in the update, in their stored form', function (): void {
    $update = new FinanceSettingsUpdate(new Money('1000.00'), null, new VatRate('5'), 20, null);

    expect($update->values())->toBe(['accredited_price' => '1000.00', 'vat_rate' => '5.00', 'order_validity_minutes' => 20])
        ->and($update->isEmpty())->toBeFalse();
});

it('is empty when no setting is given, even with a reason', function (): void {
    expect(new FinanceSettingsUpdate(null, null, null, null, 'سبب مكتوب للتغيير المطلوب.')->isEmpty())->toBeTrue();
});

it('maps every field to its setting key and permission', function (FinanceSettingField $field, SettingKey $key, PermissionKey $permission): void {
    expect($field->settingKey())->toBe($key)
        ->and($field->permission())->toBe($permission);
})->with([
    [FinanceSettingField::AccreditedPrice, SettingKey::AccreditedPrice, PermissionKey::SettingsAssessmentManage],
    [FinanceSettingField::BagPrice, SettingKey::BagPrice, PermissionKey::BagAdminManage],
    [FinanceSettingField::VatRate, SettingKey::VatRate, PermissionKey::SettingsAssessmentManage],
    [FinanceSettingField::OrderValidityMinutes, SettingKey::PaymentOrderValidityMinutes, PermissionKey::SettingsAssessmentManage],
]);
