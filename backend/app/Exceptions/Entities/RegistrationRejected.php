<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class RegistrationRejected extends ApiException
{
    private const string EMAIL_FIELD = 'official_email';

    public static function termsUnavailable(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'registration.terms_unavailable'));
    }

    public static function emailUnverified(): self
    {
        return self::onEmail('registration.email_unverified');
    }

    public static function emailTaken(): self
    {
        return self::onEmail('registration.email_taken');
    }

    private static function onEmail(string $code): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [self::EMAIL_FIELD => [__('errors.'.$code)]]));
    }
}
