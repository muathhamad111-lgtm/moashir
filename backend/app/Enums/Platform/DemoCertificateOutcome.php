<?php

declare(strict_types=1);

namespace App\Enums\Platform;

enum DemoCertificateOutcome: string
{
    case None = 'none';
    case Issued = 'issued';
    case Revoked = 'revoked';
}
