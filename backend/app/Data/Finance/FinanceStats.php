<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Support\ValueObjects\Money;

final readonly class FinanceStats
{
    public function __construct(
        public int $transactionsCount,
        public int $completedCount,
        public int $incompleteCount,
        public int $refundedCount,
        public Money $totalIncome,
        public Money $totalVat,
        public Money $totalRefunded,
        public Money $transactionsValue,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'transactions_count' => $this->transactionsCount,
            'completed_count' => $this->completedCount,
            'incomplete_count' => $this->incompleteCount,
            'refunded_count' => $this->refundedCount,
            'total_income' => $this->totalIncome->amount,
            'total_vat' => $this->totalVat->amount,
            'total_refunded' => $this->totalRefunded->amount,
            'transactions_value' => $this->transactionsValue->amount,
            'currency' => $this->totalIncome->currency,
        ];
    }
}
