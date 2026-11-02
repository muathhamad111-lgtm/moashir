<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentStatus;

final readonly class ManualSettlement
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerPaymentId,
        public string $reason,
    ) {}

    public function marksPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }
}
