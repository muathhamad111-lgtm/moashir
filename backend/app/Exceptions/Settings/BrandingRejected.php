<?php

declare(strict_types=1);

namespace App\Exceptions\Settings;

use App\Data\Settings\ContrastCheck;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class BrandingRejected extends ApiException
{
    public static function tokenUnknown(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'token.unknown', [
            'accent_token' => [__('errors.token.unknown')],
        ]));
    }

    /**
     * @param  list<ContrastCheck>  $failures
     */
    public static function contrastFail(array $failures): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'contrast_fail', [
            'accent_token' => [__('errors.contrast_fail')],
        ], [], ['pairs' => array_map(static fn (ContrastCheck $check): array => $check->toArray(), $failures)]));
    }
}
