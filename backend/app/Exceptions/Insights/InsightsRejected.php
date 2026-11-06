<?php

declare(strict_types=1);

namespace App\Exceptions\Insights;

use App\Enums\Insights\ResultIneligibility;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class InsightsRejected extends ApiException
{
    public static function resultNotEligible(ResultIneligibility $reason): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'insights.result_not_eligible', [], [], ['reason' => $reason->value]));
    }

    public static function genericTextNotDeletable(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'insights.generic_text_not_deletable'));
    }
}
