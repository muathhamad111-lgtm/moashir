<?php

declare(strict_types=1);

namespace App\Exceptions\Settings;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class SettingsRejected extends ApiException
{
    public static function reasonRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'reason_required', [
            'reason' => [__('errors.reason_required')],
        ]));
    }
}
