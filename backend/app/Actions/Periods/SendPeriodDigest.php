<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Finance\Payment;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Entities\EntityNotifier;
use App\Services\Periods\PeriodAudience;
use App\Services\Periods\PeriodLocator;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodNotices;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class SendPeriodDigest
{
    public const int TRANSFER_WARNING_DAYS = 7;

    public function __construct(
        private PeriodLocator $periods,
        private PeriodAudience $audience,
        private EntityFreezeGuard $freezeGuard,
        private EntityNotifier $notifier,
    ) {}

    public function handle(): bool
    {
        $today = LocalDay::today();
        $current = $this->periods->current();

        if (! $current instanceof AssessmentPeriod || ! $this->claimToday($current, $today)) {
            return false;
        }

        $notice = PeriodNotices::for(NotificationCode::PeriodDigest, PeriodNotices::FOR_STAFF, [
            'period' => $current->label(),
            'ends_on' => $current->ends_on->toDateString(),
            'pending_entities' => (string) $this->audience->withoutSubmissionIn($current->id)->count(),
            'paid_drafts' => (string) $this->paidDrafts(),
            'pending_transfers' => (string) $this->transfersAtRisk($current, $today),
        ], AssessmentLinks::preparingRequests(AssessmentPath::Accredited))->inAppOnly();

        $this->notifier->toRole(RoleKey::RequestFollower, $notice, null);
        $this->notifier->toRole(RoleKey::SystemAdmin, $notice, null);

        return true;
    }

    private function claimToday(AssessmentPeriod $period, CarbonImmutable $today): bool
    {
        return AssessmentPeriod::query()
            ->whereKey($period->id)
            ->where(static fn (Builder $unsent): Builder => $unsent->whereNull('last_digest_on')->orWhere('last_digest_on', '<', $today->toDateString()))
            ->update(['last_digest_on' => $today->toDateString()]) === 1;
    }

    private function paidDrafts(): int
    {
        return $this->freezeGuard->excludeFrozen(Assessment::query()->ofPath(AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Draft))
            ->whereHas('payment', static fn (Builder $payment): Builder => $payment->where('status', PaymentStatus::Paid))
            ->count();
    }

    private function transfersAtRisk(AssessmentPeriod $period, CarbonImmutable $today): int
    {
        if ($period->ends_on->subDays(self::TRANSFER_WARNING_DAYS)->toDateString() > $today->toDateString()) {
            return 0;
        }

        return Payment::query()->bankTransfers()->where('purpose', PaymentPurpose::Accredited)->where('status', PaymentStatus::UnderReview)->count();
    }
}
