<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class PhoneVerificationRejected extends ApiException
{
    public static function missing(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'phone.missing'));
    }

    public static function alreadyVerified(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'phone.already_verified'));
    }

    public static function smsUnavailable(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'phone.sms_unavailable'));
    }

    public static function inUse(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'phone.in_use'));
    }
}
