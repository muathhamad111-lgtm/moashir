<?php

declare(strict_types=1);

namespace App\Exceptions\Documents;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class DocumentRenderingFailed extends ApiException
{
    private const int RETRY_AFTER_SECONDS = 30;

    public static function busy(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'document.renderer_busy', [], ['Retry-After' => (string) self::RETRY_AFTER_SECONDS]));
    }

    public static function engineError(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'document.render_failed'));
    }
}
