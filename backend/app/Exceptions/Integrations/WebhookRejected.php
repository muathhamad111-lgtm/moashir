<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class WebhookRejected extends ApiException
{
    public static function unauthenticated(): self
    {
        return new self(new ApiError(Response::HTTP_UNAUTHORIZED, 'webhook.unauthenticated'));
    }

    public static function malformed(): self
    {
        return new self(new ApiError(Response::HTTP_BAD_REQUEST, 'webhook.malformed'));
    }
}
