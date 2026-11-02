<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class RegistrationRejected extends ApiException
{
    public static function termsUnavailable(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'registration.terms_unavailable'));
    }
}
