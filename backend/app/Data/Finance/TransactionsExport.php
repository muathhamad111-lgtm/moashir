<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Models\Finance\FinanceExport;
use App\Models\Finance\Payment;
use Illuminate\Support\LazyCollection;

final readonly class TransactionsExport
{
    /**
     * @param  LazyCollection<int, Payment>|null  $payments
     */
    public function __construct(
        public FinanceExport $export,
        public string $fileName,
        public ?LazyCollection $payments,
    ) {}

    public function isQueued(): bool
    {
        return ! $this->payments instanceof LazyCollection;
    }
}
