<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class EntityDocumentRejected extends ApiException
{
    public static function missing(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'entity.document_missing'));
    }
}
