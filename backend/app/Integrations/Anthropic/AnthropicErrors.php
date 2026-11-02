<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Integrations\Ai\AiRetryPolicy;
use Illuminate\Http\Client\Response;

final class AnthropicErrors
{
    private const array SPEND_LIMIT_MARKERS = ['credit balance', 'spend limit', 'usage limit', 'billing'];

    public static function throwIfFailed(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $type = $response->json('error.type');
        $type = is_string($type) ? $type : null;

        throw ProviderCallFailed::because(IntegrationProvider::Anthropic, $operation, self::kindOf($response, $type), $response->status(), $type);
    }

    private static function kindOf(Response $response, ?string $type): ProviderErrorKind
    {
        $status = $response->status();

        return match (true) {
            $status === 402 || $type === 'billing_error' => ProviderErrorKind::QuotaExhausted,
            in_array($status, [401, 403], true) => ProviderErrorKind::AuthFailed,
            $status === 413 => ProviderErrorKind::RequestTooLarge,
            $status === 429 => $response->header('retry-after') === '' ? ProviderErrorKind::QuotaExhausted : self::rateLimitKind($response),
            $status === 400 && self::mentionsSpendLimit($response) => ProviderErrorKind::QuotaExhausted,
            $status >= 500 => ProviderErrorKind::Unavailable,
            default => ProviderErrorKind::Rejected,
        };
    }

    private static function rateLimitKind(Response $response): ProviderErrorKind
    {
        return AiRetryPolicy::isQuickRetry($response) ? ProviderErrorKind::RateLimited : ProviderErrorKind::Unavailable;
    }

    private static function mentionsSpendLimit(Response $response): bool
    {
        $message = $response->json('error.message');
        $text = is_string($message) ? mb_strtolower($message) : '';

        return array_any(self::SPEND_LIMIT_MARKERS, static fn (string $marker): bool => str_contains($text, $marker));
    }
}
