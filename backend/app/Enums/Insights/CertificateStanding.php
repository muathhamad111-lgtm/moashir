<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum CertificateStanding: string
{
    case Level = 'level';
    case Participation = 'participation';
    case NotIssued = 'not_issued';
}
