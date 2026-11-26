<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

enum CardFormat: string
{
    case Count = 'count';
    case Percent = 'percent';
    case Money = 'money';
    case Days = 'days';
    case Date = 'date';
}
