<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Payment;
use App\Support\CsvCell;
use App\Support\ScalarValue;
use Illuminate\Support\LazyCollection;

final class TransactionCsvWriter
{
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    private const array COLUMNS = [
        'reference', 'receipt_number', 'created_at', 'paid_at', 'purpose', 'entity_reference', 'entity_name', 'entity_email',
        'method', 'status', 'price_before_vat', 'vat_rate', 'vat_amount', 'total', 'currency', 'refunded_at', 'refund_amount',
    ];

    /**
     * @param  LazyCollection<int, Payment>  $payments
     * @param  resource  $stream
     */
    public function write(LazyCollection $payments, string $locale, $stream): void
    {
        fwrite($stream, self::BYTE_ORDER_MARK);
        $this->writeRow($stream, array_map(fn (string $column): string => $this->label('finance.export.columns.'.$column, $locale), self::COLUMNS));

        foreach ($payments as $payment) {
            $this->writeRow($stream, $this->row($payment, $locale));
        }
    }

    /**
     * @return list<string>
     */
    public function row(Payment $payment, string $locale): array
    {
        return [
            $payment->reference,
            $payment->receipt->number ?? '',
            $payment->created_at->toIso8601String(),
            $payment->paid_at?->toIso8601String() ?? '',
            $this->label('finance.purposes.'.$payment->purpose->value, $locale),
            $payment->entity->reference(),
            $payment->entity_name->in($locale),
            $payment->entity->owner->email ?? '',
            $payment->method === null ? '' : $this->label('finance.methods.'.$payment->method->value, $locale),
            $this->label('finance.statuses.'.$payment->status->value, $locale),
            $payment->price_before_vat,
            $payment->vat_rate,
            $payment->vat_amount,
            $payment->total,
            $payment->currency,
            $payment->refunded_at?->toIso8601String() ?? '',
            $payment->refund->amount ?? '',
        ];
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $cells
     */
    private function writeRow($stream, array $cells): void
    {
        fputcsv($stream, array_map(CsvCell::neutralize(...), $cells), ',', '"', '');
    }

    private function label(string $key, string $locale): string
    {
        return ScalarValue::string(__($key, [], $locale));
    }
}
