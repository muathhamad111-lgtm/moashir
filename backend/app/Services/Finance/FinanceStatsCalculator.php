<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\FinanceStats;
use App\Data\Finance\MyPaymentStats;
use App\Models\Finance\Payment;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;

final class FinanceStatsCalculator
{
    private const string AGGREGATES = <<<'SQL'
        count(*) as transactions_count,
        count(*) filter (where status = 'paid') as completed_count,
        count(*) filter (where status in ('created', 'pending', 'failed', 'cancelled')) as incomplete_count,
        count(*) filter (where status = 'refunded') as refunded_count,
        coalesce(sum(total) filter (where status = 'paid'), 0) * 100 as income_halalas,
        coalesce(sum(vat_amount) filter (where status = 'paid'), 0) * 100 as vat_halalas,
        coalesce(sum(total) filter (where status = 'refunded'), 0) * 100 as refunded_halalas
        SQL;

    /**
     * @param  Builder<Payment>  $payments
     */
    public function overall(Builder $payments): FinanceStats
    {
        $row = $this->aggregate($payments);

        return new FinanceStats(
            ScalarValue::int($row['transactions_count'] ?? null),
            ScalarValue::int($row['completed_count'] ?? null),
            ScalarValue::int($row['incomplete_count'] ?? null),
            ScalarValue::int($row['refunded_count'] ?? null),
            $this->money($row['income_halalas'] ?? null),
            $this->money($row['vat_halalas'] ?? null),
            $this->money($row['refunded_halalas'] ?? null),
        );
    }

    /**
     * @param  Builder<Payment>  $payments
     */
    public function ofEntity(Builder $payments): MyPaymentStats
    {
        $stats = $this->overall($payments);

        return new MyPaymentStats($stats->transactionsCount, $stats->completedCount, $stats->totalIncome, $stats->totalVat);
    }

    /**
     * @param  Builder<Payment>  $payments
     * @return array<string, mixed>
     */
    private function aggregate(Builder $payments): array
    {
        $row = (clone $payments)->reorder()->toBase()->selectRaw(self::AGGREGATES)->first();
        $values = [];

        foreach (is_object($row) ? get_object_vars($row) : [] as $column => $value) {
            $values[(string) $column] = $value;
        }

        return $values;
    }

    private function money(mixed $halalas): Money
    {
        $value = ScalarValue::string($halalas, '0');

        return Money::fromMinorUnits((int) explode('.', $value)[0]);
    }
}
