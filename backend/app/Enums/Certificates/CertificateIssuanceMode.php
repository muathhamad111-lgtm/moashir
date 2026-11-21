<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

/**
 * How an accredited certificate comes to exist once the assessment is completed.
 */
enum CertificateIssuanceMode: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
