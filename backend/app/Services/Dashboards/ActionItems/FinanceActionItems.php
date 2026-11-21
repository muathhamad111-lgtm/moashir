<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;
use Illuminate\Support\Facades\Date;

final class FinanceActionItems implements ActionItemSource
{
    private const int OPENING_GRACE_MINUTES = 30;

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::FinanceRefundsManage->value);
    }

    public function itemsFor(User $user): array
    {
        return array_values(array_filter([
            $this->item('record_refunds', $this->unusedPayments(), PaymentStatus::Paid, null),
            $this->item('award_paid_after_window', $this->awardPaidAfterWindow(), PaymentStatus::Paid, PaymentPurpose::Award),
        ]));
    }

    private function unusedPayments(): int
    {
        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereIn('purpose', PaymentPurpose::pathPurposes())
            ->where('paid_at', '<', Date::now()->subMinutes(self::OPENING_GRACE_MINUTES))
            ->whereNotIn('id', Assessment::query()->select('payment_id')->whereNotNull('payment_id'))
            ->count();
    }

    private function awardPaidAfterWindow(): int
    {
        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->where('purpose', PaymentPurpose::Award)
            ->whereIn('id', Assessment::query()->select('payment_id')->whereNotNull('payment_id'))
            ->whereExists(AwardCycle::query()
                ->selectRaw('1')
                ->whereColumn('award_cycles.id', 'payments.award_cycle_id')
                ->whereColumn('award_cycles.submission_end', '<', 'payments.paid_at'))
            ->count();
    }

    private function item(string $key, int $count, PaymentStatus $status, ?PaymentPurpose $purpose): ?ActionItem
    {
        if ($count === 0) {
            return null;
        }

        $query = array_filter(['purpose' => $purpose?->value, 'status' => $status->value]);

        return new ActionItem($key, $count, ActionSeverity::Danger, Links::FINANCE_TRANSACTIONS.'?'.http_build_query($query));
    }
}
