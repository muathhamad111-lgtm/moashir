<?php

declare(strict_types=1);

namespace App\Exceptions\Console;

use RuntimeException;

final class BreachedListUnreadable extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self(sprintf('The breached-password list [%s] does not exist or cannot be read.', basename($path)));
    }
}
