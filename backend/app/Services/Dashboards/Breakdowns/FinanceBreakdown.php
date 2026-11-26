<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Services\Finance\AbandonedCarts;
use App\Services\Finance\BankTransferQuery;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\PeriodWindow;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class FinanceBreakdown implements OverviewBreakdownSource
{
    private const string MONEY_ZERO = '0.00';

    private const string VAT_SUM = 'cast(coalesce(sum(vat_amount), 0) as numeric(14, 2)) as amount';

    private const string REFUND_SUM = 'cast(coalesce(sum(amount), 0) as numeric(14, 2)) as amount';

    public function __construct(private BankTransferQuery $transfers, private AbandonedCarts $carts) {}

    public function keys(): array
    {
        return [OverviewBreakdownKey::Finance];
    }

    /**
     * @return array{finance: array{vat_collected: string, refunds_total: string, receipts_issued: int, transfers_awaiting_review: int, abandoned_carts: int}}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        return [OverviewBreakdownKey::Finance->value => [
            'vat_collected' => $this->vatCollected($period, $filter),
            'refunds_total' => $this->refundsTotal($period, $filter),
            'receipts_issued' => $this->receiptsIssued($period, $filter),
            'transfers_awaiting_review' => $this->transfersAwaitingReview($filter),
            'abandoned_carts' => $this->abandonedCarts($period, $filter),
        ]];
    }

    private function vatCollected(ReportingPeriod $period, BoardFilter $filter): string
    {
        $paid = Payment::query()->where('status', PaymentStatus::Paid->value);
        PeriodWindow::moments($paid, 'paid_at', $period);
        $filter->applyToPayments($paid);

        return $this->money($paid, self::VAT_SUM);
    }

    private function refundsTotal(ReportingPeriod $period, BoardFilter $filter): string
    {
        $refunds = Refund::query()->whereIn('payment_id', $this->matchingPayments($filter));
        PeriodWindow::days($refunds, 'refunded_on', $period);

        return $this->money($refunds, self::REFUND_SUM);
    }

    private function receiptsIssued(ReportingPeriod $period, BoardFilter $filter): int
    {
        $receipts = PaymentReceipt::query()->where('is_test', false)->whereIn('payment_id', $this->matchingPayments($filter));
        PeriodWindow::moments($receipts, 'issued_at', $period);

        return $receipts->count();
    }

    private function transfersAwaitingReview(BoardFilter $filter): int
    {
        $awaiting = $this->transfers->awaitingReview();
        $filter->applyToPayments($awaiting);

        return $awaiting->count();
    }

    private function abandonedCarts(ReportingPeriod $period, BoardFilter $filter): int
    {
        $carts = $this->carts->query();
        PeriodWindow::moments($carts, 'last_seen_at', $period);
        $filter->applyToPayments($carts, 'checkout_intents');

        return $carts->count();
    }

    /**
     * @return Builder<Payment>
     */
    private function matchingPayments(BoardFilter $filter): Builder
    {
        $payments = Payment::query()->select('payments.id');
        $filter->applyToPayments($payments);

        return $payments;
    }

    /**
     * @param  Builder<Payment>|Builder<Refund>  $query
     * @param  literal-string  $sum
     */
    private function money(Builder $query, string $sum): string
    {
        $row = AggregateRow::fetch($query, $sum);

        return ScalarValue::string($row['amount'] ?? null, self::MONEY_ZERO);
    }
}
