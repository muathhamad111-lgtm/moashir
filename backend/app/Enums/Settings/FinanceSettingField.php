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

    public function permission(): PermissionKey
    {
        return match ($this) {
            self::VatRegistrationNumber => PermissionKey::SettingsPaymentManage,
            default => PermissionKey::SettingsAssessmentManage,
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
