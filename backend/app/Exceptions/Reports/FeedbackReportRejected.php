<?php

declare(strict_types=1);

namespace App\Exceptions\Reports;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class FeedbackReportRejected extends ApiException
{
    public static function notAvailable(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'report.not_available'));
    }
}
