<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Json = 'json';
}
