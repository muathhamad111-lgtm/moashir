<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Support\ValueObjects\SecretValue;
use Illuminate\Support\Str;

final class DemoPasswordGenerator
{
    private const int LENGTH = 24;

    public function generate(): SecretValue
    {
        return new SecretValue(Str::password(self::LENGTH, symbols: false));
    }
}
