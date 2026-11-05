<?php

declare(strict_types=1);

namespace App\Exceptions\Audit;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class AuditLogRejected extends ApiException
{
    public static function exportTooLarge(int $limit): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'export.too_large', [], [], ['limit' => $limit]));
    }

    public static function reasonRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'reason_required'));
    }
}
