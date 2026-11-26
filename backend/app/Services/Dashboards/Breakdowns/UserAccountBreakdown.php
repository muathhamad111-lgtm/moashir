<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\User;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\PeriodWindow;
use Illuminate\Support\Facades\Date;

final readonly class UserAccountBreakdown implements OverviewBreakdownSource
{
    private const string STAFF_ACTIVE = 'status = ? and entity_id is null and is_super_admin = false';

    private const int PERCENT = 100;

    private const int SHARE_DECIMALS = 1;

    public function keys(): array
    {
        return [OverviewBreakdownKey::Users];
    }

    /**
     * @return array{users: array{staff_active: int, entity_accounts: int, mfa_share: float|null, locked: int, failed_logins: int}}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $active = UserStatus::Active->value;
        $counts = AggregateRow::counts(User::query(), [
            'staff_active' => [self::STAFF_ACTIVE, [$active]],
            'staff_with_mfa' => [self::STAFF_ACTIVE.' and two_factor_confirmed_at is not null', [$active]],
            'entity_accounts' => ['status = ? and entity_id is not null', [$active]],
            'locked' => ['locked_until > ?', [Date::now()->toIso8601String()]],
        ]);
        $staff = $counts['staff_active'] ?? 0;
        $failedLogins = AuthenticationEvent::query()->where('type', AuthEventType::LoginFailed->value);
        PeriodWindow::moments($failedLogins, 'created_at', $period);

        return [OverviewBreakdownKey::Users->value => [
            'staff_active' => $staff,
            'entity_accounts' => $counts['entity_accounts'] ?? 0,
            'mfa_share' => $staff > 0 ? round(($counts['staff_with_mfa'] ?? 0) * self::PERCENT / $staff, self::SHARE_DECIMALS) : null,
            'locked' => $counts['locked'] ?? 0,
            'failed_logins' => $failedLogins->count(),
        ]];
    }
}
