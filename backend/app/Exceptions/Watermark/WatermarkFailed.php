<?php

declare(strict_types=1);

namespace App\Exceptions\Watermark;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class WatermarkFailed extends ApiException
{
    private const int RETRY_AFTER_SECONDS = 60;

    public static function unreadable(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'file.watermark_failed'));
    }

    public static function conversionFailed(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'file.conversion_failed'));
    }

    public static function preparing(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'file.preparing', [], ['Retry-After' => (string) self::RETRY_AFTER_SECONDS]));
    }

    public static function tooLarge(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'file.too_large_to_stamp'));
    }
}
