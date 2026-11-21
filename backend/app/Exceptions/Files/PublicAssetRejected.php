<?php

declare(strict_types=1);

namespace App\Exceptions\Files;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class PublicAssetRejected extends ApiException
{
    public static function unusable(string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'file.not_usable', [
            $field => [__('errors.file.not_usable')],
        ]));
    }
}
