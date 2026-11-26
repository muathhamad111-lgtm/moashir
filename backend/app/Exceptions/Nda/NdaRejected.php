<?php

declare(strict_types=1);

namespace App\Exceptions\Nda;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class NdaRejected extends ApiException
{
    public static function acceptanceRequired(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'nda.acceptance_required'));
    }

    public static function versionNotCurrent(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'nda.version_not_current', [
            'terms_version_id' => [__('errors.nda.version_not_current')],
        ]));
    }

    public static function noCurrentVersion(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'nda.no_current_version'));
    }

    public static function noDraft(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'nda.no_draft'));
    }

    public static function reminderTooSoon(int $retryAfterSeconds): self
    {
        return new self(new ApiError(Response::HTTP_TOO_MANY_REQUESTS, 'nda.reminder_too_soon', [], ['Retry-After' => (string) max(1, $retryAfterSeconds)]));
    }
}
