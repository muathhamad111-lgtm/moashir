<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class IdentityChangeRejected extends ApiException
{
    public static function alreadyPending(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'identity_change.already_pending'));
    }

    public static function nothingToChange(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'identity_change.nothing_to_change'));
    }

    public static function ownRequest(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'identity_change.own_request'));
    }
}
