<?php

declare(strict_types=1);

namespace App\Exceptions\Finance;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class FinanceExportRejected extends ApiException
{
    public static function notReady(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'finance_export.not_ready'));
    }
}
