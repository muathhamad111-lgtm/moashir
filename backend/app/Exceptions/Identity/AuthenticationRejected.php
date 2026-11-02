<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class AuthenticationRejected extends ApiException
{
    public static function invalidCredentials(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'credentials.invalid'));
    }

    public static function accountDeactivated(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'account_deactivated'));
    }

    public static function accountLocked(int $retryAfterSeconds): self
    {
        return new self(new ApiError(423, 'account_locked', [], ['Retry-After' => (string) max(1, $retryAfterSeconds)]));
    }

    public static function sessionExpired(): self
    {
        return new self(new ApiError(Response::HTTP_UNAUTHORIZED, 'session.expired'));
    }

    public static function modeDisabled(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'login.mode_disabled'));
    }

    public static function passwordChangeRequired(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'password.change_required'));
    }
}
