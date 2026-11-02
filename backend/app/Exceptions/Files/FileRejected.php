<?php

declare(strict_types=1);

namespace App\Exceptions\Files;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class FileRejected extends ApiException
{
    public static function contentMismatch(string $field): self
    {
        return self::fieldError('file.content_mismatch', $field);
    }

    public static function infected(string $field): self
    {
        return self::fieldError('file.infected', $field);
    }

    public static function scanFailed(string $field): self
    {
        return self::fieldError('file.scan_failed', $field);
    }

    public static function notClean(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'file.not_clean'));
    }

    public static function signatureInvalid(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'signature.invalid'));
    }

    public static function signatureExpired(): self
    {
        return new self(new ApiError(Response::HTTP_GONE, 'signature.expired'));
    }

    private static function fieldError(string $code, string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [$field => [__('errors.'.$code)]]));
    }
}
