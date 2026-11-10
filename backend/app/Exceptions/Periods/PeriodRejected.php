<?php

declare(strict_types=1);

namespace App\Exceptions\Periods;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\PeriodPresenter;
use Illuminate\Http\Response;

final class PeriodRejected extends ApiException
{
    public static function alreadyAssessed(AssessmentPeriod $current, ?AssessmentPeriod $next, int $noticeDays): self
    {
        $nextStart = ($next->starts_on ?? $current->ends_on->addDay())->toDateString();

        return new self(new ApiError(Response::HTTP_CONFLICT, 'period.already_assessed', [], [], [
            'next_submission_from' => $nextStart,
            'current_period' => PeriodPresenter::period($current),
            'next_period' => PeriodPresenter::upcoming($next, $noticeDays),
        ]));
    }
}
