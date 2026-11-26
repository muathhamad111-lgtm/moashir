<?php

declare(strict_types=1);

namespace App\Exceptions\Platform;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class ExportStreamUnavailable extends ApiException
{
    public static function cannotOpen(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'export.stream_unavailable'));
    }
}
