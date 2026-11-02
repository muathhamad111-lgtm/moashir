<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Support\ValueObjects\Money;

final readonly class MyPaymentStats
{
    public function __construct(
        public int $count,
        public int $paidCount,
        public Money $totalPaid,
        public Money $totalVat,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'paid_count' => $this->paidCount,
            'total_paid' => $this->totalPaid->amount,
            'total_vat' => $this->totalVat->amount,
            'currency' => $this->totalPaid->currency,
        ];
    }
}
