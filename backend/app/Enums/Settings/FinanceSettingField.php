<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Enums\Identity\PermissionKey;

enum FinanceSettingField: string
{
    case VatRate = 'vat_rate';
    case OrderValidityMinutes = 'order_validity_minutes';
    case VatRegistrationNumber = 'vat_registration_number';

    public const string ACCREDITED_PRICE_MIRROR = 'accredited_price';

    public const string BAG_PRICE_MIRROR = 'bag_price';

    public function settingKey(): SettingKey
    {
        return match ($this) {
            self::VatRate => SettingKey::VatRate,
            self::OrderValidityMinutes => SettingKey::PaymentOrderValidityMinutes,
            self::VatRegistrationNumber => SettingKey::VatRegistrationNumber,
        };
    }

    /**
     * The permission that lets a role edit the field. Null: no permission does; the statutory VAT rate is fixed and
     * only the Super Admin (who passes every gate) may change it, with a reason (operator decision 2026-11-25).
     */
    public function permission(): ?PermissionKey
    {
        return match ($this) {
            self::VatRate => null,
            self::OrderValidityMinutes => PermissionKey::SettingsAssessmentManage,
            self::VatRegistrationNumber => PermissionKey::SettingsPaymentManage,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function priceMirrors(): array
    {
        return [self::ACCREDITED_PRICE_MIRROR, self::BAG_PRICE_MIRROR];
    }
}
