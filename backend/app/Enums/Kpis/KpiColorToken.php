<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

enum KpiColorToken: string
{
    case Primary = 'primary';
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';
}
