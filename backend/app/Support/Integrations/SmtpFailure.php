<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Enums\Integrations\ProviderErrorKind;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class SmtpFailure
{
    public static function kindOf(TransportExceptionInterface $exception): ProviderErrorKind
    {
        $message = mb_strtolower($exception->getMessage());

        return match (true) {
            in_array($exception->getCode(), [535, 534, 530], true) || str_contains($message, 'authenticat') => ProviderErrorKind::AuthFailed,
            str_contains($message, 'timed out') || str_contains($message, 'timeout') => ProviderErrorKind::Timeout,
            str_contains($message, 'tls') || str_contains($message, 'ssl') || str_contains($message, 'certificate') => ProviderErrorKind::Misconfigured,
            default => ProviderErrorKind::Unavailable,
        };
    }
}
