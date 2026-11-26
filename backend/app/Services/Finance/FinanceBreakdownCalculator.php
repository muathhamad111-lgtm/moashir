<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\ChannelStats;
use App\Data\Finance\FinanceBreakdown;
use App\Data\Finance\StatusCounts;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Splits the operations of a filtered list by status and by payment channel, for the finance overview cards.
 */
final class FinanceBreakdownCalculator
{
    private const string AGGREGATES = 'channel, status, count(*) as operations, coalesce(sum(total), 0) * 100 as total_halalas';

    /**
     * @param  Builder<Payment>  $payments
     */
    public function of(Builder $payments): FinanceBreakdown
    {
        $groups = $this->groups($payments);
        $channels = [];

        foreach (PaymentChannel::cases() as $channel) {
            $channels[$channel->value] = $this->channelStats($groups[$channel->value] ?? []);
        }

        return new FinanceBreakdown(StatusCounts::fromPaymentStatuses($this->countsOf(array_merge(...array_values($groups)))), $channels);
    }

    /**
     * @param  list<array{status: string, operations: int, halalas: int}>  $rows
     */
    private function channelStats(array $rows): ChannelStats
    {
        $paid = array_filter($rows, static fn (array $row): bool => $row['status'] === PaymentStatus::Paid->value);

        return new ChannelStats(
            StatusCounts::fromPaymentStatuses($this->countsOf($rows)),
            Money::fromMinorUnits(array_sum(array_column($paid, 'halalas'))),
        );
    }

    /**
     * @param  list<array{status: string, operations: int, halalas: int}>  $rows
     * @return array<string, int>
     */
    private function countsOf(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + $row['operations'];
        }

        return $counts;
    }

    /**
     * @param  Builder<Payment>  $payments
     * @return array<string, list<array{status: string, operations: int, halalas: int}>>
     */
    private function groups(Builder $payments): array
    {
        $rows = (clone $payments)->reorder()->toBase()->selectRaw(self::AGGREGATES)->groupBy('channel', 'status')->get();
        $groups = [];

        foreach ($rows as $row) {
            $values = is_object($row) ? get_object_vars($row) : [];
            $groups[ScalarValue::string($values['channel'] ?? null)][] = [
                'status' => ScalarValue::string($values['status'] ?? null),
                'operations' => ScalarValue::int($values['operations'] ?? null),
                'halalas' => (int) explode('.', ScalarValue::string($values['total_halalas'] ?? null, '0'))[0],
            ];
        }

        return $groups;
    }
}
