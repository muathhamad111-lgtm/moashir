<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum ProviderErrorKind: string
{
    case AuthFailed = 'auth_failed';
    case AccountInactive = 'account_inactive';
    case Misconfigured = 'misconfigured';
    case RecipientInvalid = 'recipient_invalid';
    case Rejected = 'rejected';
    case QuotaExhausted = 'quota_exhausted';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';
    case Timeout = 'timeout';
    case RequestTooLarge = 'request_too_large';
    case Unknown = 'unknown';

    public function isTransient(): bool
    {
        return in_array($this, [self::Unavailable, self::Timeout, self::RateLimited], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
