<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

enum EntityLevel: string
{
    case Participation = 'participation';
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Diamond = 'diamond';

    public static function ofCertificate(?CertificateLevel $level): self
    {
        return $level instanceof CertificateLevel ? self::from($level->value) : self::Participation;
    }
}
