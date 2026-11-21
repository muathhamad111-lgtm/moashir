<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class NationalIdRejected extends ApiException
{
    private const string FIELD = 'national_id';

    public static function locked(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'identity.national_id_locked'));
    }

    public static function unavailable(): self
    {
        return self::onField('identity.national_id_unavailable');
    }

    public static function missing(): self
    {
        return self::onField('identity.national_id_required');
    }

    private static function onField(string $code): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [self::FIELD => [__('errors.'.$code)]]));
    }
}
