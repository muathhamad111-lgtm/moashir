<?php

declare(strict_types=1);

namespace App\Exceptions\Files;

use RuntimeException;

final class FileScannerUnavailable extends RuntimeException
{
    public static function unreachable(): self
    {
        return new self('The malware scanner could not be reached.');
    }
}
