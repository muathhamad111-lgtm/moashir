<?php

declare(strict_types=1);

namespace App\Exceptions\Dashboards;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class DashboardRejected extends ApiException
{
    public static function boardNotHeld(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'board.not_held'));
    }
}
