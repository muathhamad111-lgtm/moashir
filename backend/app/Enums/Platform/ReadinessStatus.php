<?php

declare(strict_types=1);

namespace App\Enums\Platform;

use Illuminate\Http\Response;

enum ReadinessStatus: string
{
    case Ready = 'ready';
    case Degraded = 'degraded';
    case NotReady = 'not_ready';

    public function httpStatus(): int
    {
        return $this === self::NotReady ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK;
    }
}
