<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Enums\Dashboards\PaymentStatusGroup;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Support\Dashboards\PeriodWindow;
use App\Support\ScalarValue;

final readonly class PaymentStatusBreakdown implements OverviewBreakdownSource
{
    public function keys(): array
    {
        return [OverviewBreakdownKey::PaymentsByStatus];
    }

    /**
     * @return array{payments_by_status: list<array{key: string, count: int}>}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $counts = array_fill_keys(array_column(PaymentStatusGroup::cases(), 'value'), 0);
        $created = Payment::query()->live();
        PeriodWindow::moments($created, 'payments.created_at', $period);
        $filter->applyToPayments($created);

        foreach ($created->toBase()->reorder()->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            $values = get_object_vars($row);
            $status = PaymentStatus::tryFrom(ScalarValue::string($values['status'] ?? null));

            if ($status instanceof PaymentStatus) {
                $counts[PaymentStatusGroup::of($status)->value] += ScalarValue::int($values['total'] ?? null);
            }
        }

        return [OverviewBreakdownKey::PaymentsByStatus->value => array_map(
            static fn (string $key, int $count): array => ['key' => $key, 'count' => $count],
            array_keys($counts),
            array_values($counts),
        )];
    }
}
