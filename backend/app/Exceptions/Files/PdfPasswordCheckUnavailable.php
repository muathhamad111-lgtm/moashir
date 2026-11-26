<?php

declare(strict_types=1);

namespace App\Exceptions\Files;

use RuntimeException;

final class PdfPasswordCheckUnavailable extends RuntimeException
{
    public static function exitedWith(?int $exitCode): self
    {
        return new self('The PDF password check exited with code '.($exitCode ?? 'none').'.');
    }

    public static function missingFile(): self
    {
        return new self('The uploaded file to check for a PDF password is missing.');
    }

    public static function timedOut(): self
    {
        return new self('The PDF password check timed out.');
    }
}
