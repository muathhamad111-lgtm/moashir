<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Finance\PaymentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;
use Carbon\CarbonInterface;

final class PaidDraftReminderWindow
{
    public const int HOURS_BETWEEN_REMINDERS = 24;

    public static function isPaidDraft(Assessment $assessment): bool
    {
        return $assessment->isAccredited()
            && $assessment->status === AccreditedAssessmentStatus::Draft
            && $assessment->payment instanceof Payment
            && $assessment->payment->status === PaymentStatus::Paid;
    }

    public static function nextRemindAt(Assessment $assessment): ?CarbonInterface
    {
        return $assessment->paid_draft_reminded_at?->copy()->addHours(self::HOURS_BETWEEN_REMINDERS);
    }

    public static function allowsReminderAt(Assessment $assessment, CarbonInterface $now): bool
    {
        $next = self::nextRemindAt($assessment);

        return ! $next instanceof CarbonInterface || $now->greaterThanOrEqualTo($next);
    }
}
