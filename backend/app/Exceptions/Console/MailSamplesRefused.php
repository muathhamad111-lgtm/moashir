<?php

declare(strict_types=1);

namespace App\Exceptions\Console;

use App\Enums\Integrations\ProviderErrorKind;
use RuntimeException;

final class MailSamplesRefused extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function inProduction(): self
    {
        return new self('production', 'Sample e-mails are never sent from production.');
    }

    public static function environmentNotAllowed(string $environment): self
    {
        return new self('environment_not_allowed', sprintf('Sample e-mails are sent from staging or local only, not from [%s].', $environment));
    }

    public static function smtpNotConfigured(): self
    {
        return new self('smtp_not_configured', 'E-mail delivery is not configured: save and activate the SMTP settings in Settings > Integrations, then run the connection test.');
    }

    public static function deliveryFailed(ProviderErrorKind $kind, int $sent): self
    {
        return new self('delivery_failed', sprintf('The mail server refused a sample e-mail (%s) after %d were sent.', $kind->value, $sent));
    }
}
