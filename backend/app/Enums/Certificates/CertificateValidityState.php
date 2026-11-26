<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

enum CertificateValidityState: string
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';

    public static function forDaysLeft(int $daysLeft, int $expiringSoonDays): self
    {
        return match (true) {
            $daysLeft < 0 => self::Expired,
            $daysLeft <= $expiringSoonDays => self::ExpiringSoon,
            default => self::Valid,
        };
    }
}
