<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

enum ActionSeverity: string
{
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';

    public function rank(): int
    {
        return match ($this) {
            self::Danger => 0,
            self::Warning => 1,
            self::Info => 2,
        };
    }
}
