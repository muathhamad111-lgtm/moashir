<?php

declare(strict_types=1);

namespace App\Exceptions\Console;

use RuntimeException;

final class IntegrationCommandRejected extends RuntimeException
{
    public static function alreadyConfigured(string $kind): self
    {
        return new self(sprintf('The %s integration is already configured in the settings; nothing was imported.', $kind));
    }

    public static function nothingToImport(string $kind): self
    {
        return new self(sprintf('The environment holds no complete %s credentials.', $kind));
    }
}
