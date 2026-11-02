<?php

declare(strict_types=1);

namespace App\Integrations\OpenAi;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use Illuminate\Http\Client\Response;

final class OpenAiErrors
{
    private const array QUOTA_CODES = ['insufficient_quota', 'billing_hard_limit_reached', 'billing_not_active'];

    public static function throwIfFailed(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $code = self::errorCode($response);

        throw ProviderCallFailed::because(IntegrationProvider::OpenAi, $operation, self::kindOf($response, $code), $response->status(), $code);
    }

    private static function errorCode(Response $response): ?string
    {
        $code = $response->json('error.code') ?? $response->json('error.type');

        return is_string($code) ? $code : null;
    }

    private static function kindOf(Response $response, ?string $code): ProviderErrorKind
    {
        return match (true) {
            in_array($code, self::QUOTA_CODES, true) => ProviderErrorKind::QuotaExhausted,
            in_array($response->status(), [401, 403], true) => ProviderErrorKind::AuthFailed,
            $response->status() === 413 => ProviderErrorKind::RequestTooLarge,
            $response->status() === 429 => ProviderErrorKind::RateLimited,
            $response->serverError() => ProviderErrorKind::Unavailable,
            default => ProviderErrorKind::Rejected,
        };
    }
}
