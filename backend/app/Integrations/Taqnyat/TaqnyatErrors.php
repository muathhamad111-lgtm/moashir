<?php

declare(strict_types=1);

namespace App\Integrations\Taqnyat;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use Illuminate\Http\Client\Response;

final class TaqnyatErrors
{
    private const array MESSAGE_KINDS = [
        'balance' => ProviderErrorKind::QuotaExhausted,
        'sender' => ProviderErrorKind::Misconfigured,
        'mobile' => ProviderErrorKind::RecipientInvalid,
        'sending by api' => ProviderErrorKind::Misconfigured,
        'ip not authorized' => ProviderErrorKind::Misconfigured,
        'country not authorized' => ProviderErrorKind::Misconfigured,
        'proxy' => ProviderErrorKind::Misconfigured,
        'not responding' => ProviderErrorKind::Unavailable,
        'stopped' => ProviderErrorKind::Rejected,
        'no data' => ProviderErrorKind::Rejected,
    ];

    public static function throwIfFailed(Response $response, string $operation): void
    {
        $statusCode = $response->json('statusCode');
        $bodyStatus = is_int($statusCode) ? $statusCode : $response->status();

        if ($response->successful() && $bodyStatus >= 200 && $bodyStatus < 300) {
            return;
        }

        throw ProviderCallFailed::because(IntegrationProvider::Taqnyat, $operation, self::kindOf($response), $response->status());
    }

    private static function kindOf(Response $response): ProviderErrorKind
    {
        return match (true) {
            $response->status() === 401 => ProviderErrorKind::AuthFailed,
            $response->status() === 403 => ProviderErrorKind::Misconfigured,
            $response->status() === 429 => ProviderErrorKind::RateLimited,
            $response->serverError() => ProviderErrorKind::Unavailable,
            $response->status() === 400 => self::kindOfMessage($response->json('message')),
            default => ProviderErrorKind::Rejected,
        };
    }

    private static function kindOfMessage(mixed $message): ProviderErrorKind
    {
        $text = is_string($message) ? mb_strtolower($message) : '';

        foreach (self::MESSAGE_KINDS as $fragment => $kind) {
            if (str_contains($text, $fragment)) {
                return $kind;
            }
        }

        return ProviderErrorKind::Rejected;
    }
}
