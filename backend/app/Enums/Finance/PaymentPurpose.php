<?php

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Identity\PermissionKey;

enum PaymentPurpose: string
{
    case Accredited = 'accredited';
    case Bag = 'bag';

    public function requiredPermission(): PermissionKey
    {
        return match ($this) {
            self::Accredited => PermissionKey::AssessmentAccreditedManage,
            self::Bag => PermissionKey::BagOwnPurchase,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
