<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class MfaRejected extends ApiException
{
    public static function invalidCode(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'mfa.invalid'));
    }

    public static function required(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'mfa.required'));
    }

    public static function requiredForRole(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'mfa.required_for_role'));
    }

    public static function nothingPending(): self
    {
        return new self(new ApiError(Response::HTTP_UNAUTHORIZED, 'mfa.not_pending'));
    }

    public static function attemptsExceeded(): self
    {
        return new self(new ApiError(423, 'mfa.attempts_exceeded'));
    }

    public static function alreadyEnrolled(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'mfa.already_enrolled'));
    }

    public static function notEnrolled(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'mfa.not_enrolled'));
    }
}
