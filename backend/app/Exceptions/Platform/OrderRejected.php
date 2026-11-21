<?php

declare(strict_types=1);

namespace App\Exceptions\Platform;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class OrderRejected extends ApiException
{
    public static function incomplete(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'order.incomplete', [
            'ordered_ids' => [__('errors.order.incomplete')],
        ]));
    }
}
