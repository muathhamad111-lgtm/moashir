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
        $unused = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->where('purpose', PaymentPurpose::Accredited)
            ->where('paid_at', '<', Date::now()->subMinutes(self::OPENING_GRACE_MINUTES))
            ->whereNotIn('id', Assessment::query()->select('payment_id')->whereNotNull('payment_id'))
            ->count();

        return $unused > 0
            ? [new ActionItem('record_refunds', $unused, ActionSeverity::Danger, Links::FINANCE_TRANSACTIONS.'?'.http_build_query(['status' => PaymentStatus::Paid->value]))]
            : [];
    }
}
