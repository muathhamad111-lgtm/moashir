<?php

declare(strict_types=1);

namespace App\Integrations\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

final class AiRetryPolicy
{
    private const int MAX_RETRY_AFTER_SECONDS = 2;

    private const int DELAY_MILLISECONDS = 1000;

    public static function delay(int $attempt, mixed $exception): int
    {
        if ($exception instanceof RequestException) {
            $retryAfter = (int) $exception->response->header('retry-after');

            return max(self::DELAY_MILLISECONDS, $retryAfter * 1000);
        }

        return self::DELAY_MILLISECONDS;
    }

    public static function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $response = $exception->response;

        return $response->serverError() || ($response->status() === 429 && self::isQuickRetry($response));
    }

    public static function isQuickRetry(Response $response): bool
    {
        $retryAfter = $response->header('retry-after');

        return is_numeric($retryAfter) && (int) $retryAfter <= self::MAX_RETRY_AFTER_SECONDS;
    }
}
