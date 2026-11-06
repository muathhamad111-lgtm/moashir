<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

enum CertificateLevel: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Diamond = 'diamond';

    public const array DEFAULT_THRESHOLDS = [
        'bronze' => '60.00',
        'silver' => '70.00',
        'gold' => '80.00',
        'diamond' => '90.00',
    ];

    /**
     * @return list<self>
     */
    public static function ascending(): array
    {
        return [self::Bronze, self::Silver, self::Gold, self::Diamond];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
