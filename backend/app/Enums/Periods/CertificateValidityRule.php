<?php

declare(strict_types=1);

namespace App\Enums\Periods;

enum CertificateValidityRule: string
{
    case NextPeriodEnd = 'next_period_end';
}
