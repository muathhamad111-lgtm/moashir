<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Support\Dashboards\AggregateRow;
use App\Support\ScalarValue;

final class PaymentTotals
{
    private const string MONEY_ZERO = '0.00';

    /**
     * @return array{collected: string, vat_collected: string, refunded: string, paid_count: int, open_count: int, refunded_count: int}
     */
    public function live(): array
    {
        $open = PaymentStatus::openValues();
        $row = AggregateRow::fetch(Payment::query()->live(), 'cast(coalesce(sum(total) filter (where status = ?), 0) as numeric(14, 2)) as collected, '
            .'cast(coalesce(sum(vat_amount) filter (where status = ?), 0) as numeric(14, 2)) as vat_collected, '
            .'cast(coalesce(sum(total) filter (where status = ?), 0) as numeric(14, 2)) as refunded, '
            .'count(*) filter (where status = ?) as paid_count, '
            .'count(*) filter (where status in ('.AggregateRow::placeholders($open).')) as open_count, '
            .'count(*) filter (where status = ?) as refunded_count', [
                PaymentStatus::Paid->value, PaymentStatus::Paid->value, PaymentStatus::Refunded->value, PaymentStatus::Paid->value,
                ...$open, PaymentStatus::Refunded->value,
            ]);

        return [
            'collected' => ScalarValue::string($row['collected'] ?? null, self::MONEY_ZERO),
            'vat_collected' => ScalarValue::string($row['vat_collected'] ?? null, self::MONEY_ZERO),
            'refunded' => ScalarValue::string($row['refunded'] ?? null, self::MONEY_ZERO),
            'paid_count' => ScalarValue::int($row['paid_count'] ?? null),
            'open_count' => ScalarValue::int($row['open_count'] ?? null),
            'refunded_count' => ScalarValue::int($row['refunded_count'] ?? null),
        ];
    }
}
