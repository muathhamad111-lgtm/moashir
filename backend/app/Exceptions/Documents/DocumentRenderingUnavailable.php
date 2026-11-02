<?php

declare(strict_types=1);

namespace App\Exceptions\Documents;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class DocumentRenderingUnavailable extends ApiException
{
    public static function noRenderer(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'document.renderer_unavailable'));
    }
}
