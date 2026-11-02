<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use Illuminate\Http\Client\Response;

final class MoyasarErrors
{
    public static function throwIfFailed(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $type = self::errorType($response);

        throw ProviderCallFailed::because(IntegrationProvider::Moyasar, $operation, self::kindOf($response->status(), $type), $response->status(), $type);
    }

    public static function errorType(Response $response): ?string
    {
        $type = $response->json('type');

        return is_string($type) ? $type : null;
    }

    private static function kindOf(int $status, ?string $type): ProviderErrorKind
    {
        if ($type === 'account_inactive_error') {
            return ProviderErrorKind::AccountInactive;
        }

        return match (true) {
            $status === 401 => ProviderErrorKind::AuthFailed,
            $status === 403 => ProviderErrorKind::Misconfigured,
            $status === 405 => ProviderErrorKind::AccountInactive,
            $status === 400, $status === 404, $status === 422 => ProviderErrorKind::Rejected,
            $status === 429 => ProviderErrorKind::RateLimited,
            $status >= 500 => ProviderErrorKind::Unavailable,
            default => ProviderErrorKind::Unknown,
        };
    }
}
