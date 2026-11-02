<?php

declare(strict_types=1);

namespace App\Integrations\Msegat;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use Illuminate\Http\Client\Response;

final class MsegatResponse
{
    private const array SUCCESS_CODES = ['1', 'M0000'];

    private const array CODE_KINDS = [
        '1020' => ProviderErrorKind::AuthFailed,
        'M0002' => ProviderErrorKind::AuthFailed,
        '1060' => ProviderErrorKind::QuotaExhausted,
        '1110' => ProviderErrorKind::Misconfigured,
        'M0036' => ProviderErrorKind::Misconfigured,
        '1064' => ProviderErrorKind::Misconfigured,
        'M0037' => ProviderErrorKind::Misconfigured,
        'M0090' => ProviderErrorKind::Misconfigured,
        'M0092' => ProviderErrorKind::Misconfigured,
        'M0024' => ProviderErrorKind::Misconfigured,
        'M0029' => ProviderErrorKind::Misconfigured,
        'M0030' => ProviderErrorKind::Misconfigured,
        '1120' => ProviderErrorKind::RecipientInvalid,
        'M0008' => ProviderErrorKind::RecipientInvalid,
        '1140' => ProviderErrorKind::Rejected,
        '1061' => ProviderErrorKind::Rejected,
        'M0040' => ProviderErrorKind::Rejected,
        '1010' => ProviderErrorKind::Rejected,
        'M0001' => ProviderErrorKind::Rejected,
        '1050' => ProviderErrorKind::Rejected,
        'M0034' => ProviderErrorKind::Rejected,
    ];

    private const array CREDENTIAL_CODES = ['1010', '1020', 'M0001', 'M0002', 'M0034'];

    public static function code(Response $response): ?string
    {
        $code = $response->json('code');

        if (is_scalar($code)) {
            return (string) $code;
        }

        $body = trim($response->body());

        return preg_match('/^(M\d{4}|\d{1,4})$/', $body) === 1 ? $body : null;
    }

    public static function isSuccess(?string $code): bool
    {
        return in_array($code, self::SUCCESS_CODES, true);
    }

    public static function failure(Response $response, string $operation): ProviderCallFailed
    {
        $code = self::code($response);
        $kind = match (true) {
            $response->status() === 429 => ProviderErrorKind::RateLimited,
            $response->serverError() => ProviderErrorKind::Unavailable,
            $code !== null => self::CODE_KINDS[$code] ?? ProviderErrorKind::Unknown,
            default => ProviderErrorKind::Unknown,
        };

        return ProviderCallFailed::because(IntegrationProvider::Msegat, $operation, $kind, $response->status(), $code);
    }

    public static function isCredentialError(?string $code): bool
    {
        return in_array($code, self::CREDENTIAL_CODES, true);
    }
}
