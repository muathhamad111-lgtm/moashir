<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class AccountActionRejected extends ApiException
{
    public static function selfTarget(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'user.self'));
    }

    public static function entityStatusManaged(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'user.entity_status_managed'));
    }

    public static function invalidToken(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'token.invalid'));
    }

    public static function invitationExpired(): self
    {
        return new self(new ApiError(Response::HTTP_GONE, 'invitation.expired'));
    }

    public static function invitationInvalid(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'invitation.invalid'));
    }
}
