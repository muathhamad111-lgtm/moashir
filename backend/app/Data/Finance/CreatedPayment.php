<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Models\Finance\Payment;

final readonly class CreatedPayment
{
    public function __construct(
        public Payment $payment,
        public ?PaymentCheckout $checkout,
        public bool $isReplay,
        public bool $isExistingOrder,
    ) {}

    public function isNew(): bool
    {
        return ! $this->isReplay && ! $this->isExistingOrder;
    }
}
