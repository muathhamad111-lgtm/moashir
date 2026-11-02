<?php

declare(strict_types=1);

namespace App\Support;

final class FrontendUrl
{
    public static function to(string $path): string
    {
        return rtrim(config()->string('moashir.frontend_url'), '/').'/'.ltrim($path, '/');
    }
}
