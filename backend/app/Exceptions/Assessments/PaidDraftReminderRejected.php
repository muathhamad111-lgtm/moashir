<?php

declare(strict_types=1);

namespace App\Exceptions\Assessments;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class PaidDraftReminderRejected extends ApiException
{
    public static function notPaidDraft(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'request.not_paid_draft'));
    }

    public static function tooSoon(string $nextRemindAt): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'request.reminder_too_soon', [], [], ['next_remind_at' => $nextRemindAt]));
    }
}
