<?php

declare(strict_types=1);

namespace App\Exceptions\Console;

use RuntimeException;

final class MailSampleNotRendered extends RuntimeException
{
    public static function named(string $sample): self
    {
        return new self(sprintf('The sample e-mail [%s] produced no message.', $sample));
    }

    public static function missingValue(string $placeholder): self
    {
        return new self(sprintf('No fictional sample value is defined for the placeholder [:%s].', $placeholder));
    }
}
