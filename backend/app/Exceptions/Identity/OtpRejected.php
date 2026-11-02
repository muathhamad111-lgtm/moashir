<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class OtpRejected extends ApiException
{
    public static function invalidCode(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'otp.invalid'));
    }

    public static function expired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'otp.expired'));
    }

    public static function challengeInvalid(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'otp.challenge_invalid'));
    }

    public static function attemptsExceeded(): self
    {
        return new self(new ApiError(423, 'otp.attempts_exceeded'));
    }

    public static function cooldown(int $retryAfterSeconds): self
    {
        return new self(new ApiError(Response::HTTP_TOO_MANY_REQUESTS, 'otp.cooldown', [], ['Retry-After' => (string) max(1, $retryAfterSeconds)]));
    }
}
