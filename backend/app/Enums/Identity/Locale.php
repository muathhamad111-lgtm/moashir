<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum Locale: string
{
    case Arabic = 'ar';
    case English = 'en';

    public function direction(): string
    {
        return $this === self::English ? 'ltr' : 'rtl';
    }
}
