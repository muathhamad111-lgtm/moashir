<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

enum CertificateStatus: string
{
    case Issued = 'issued';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Superseded = 'superseded';
}
