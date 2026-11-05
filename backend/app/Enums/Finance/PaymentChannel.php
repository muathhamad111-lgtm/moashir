<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentChannel: string
{
    case Electronic = 'electronic';
    case BankTransfer = 'bank_transfer';

    public function initialStatus(): PaymentStatus
    {
        return match ($this) {
            self::Electronic => PaymentStatus::Created,
            self::BankTransfer => PaymentStatus::UnderReview,
        };
    }

    public function allowsTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        return in_array($to, $this->nextStatesFrom($from), true);
    }

    public function providerName(): ?string
    {
        return match ($this) {
            self::Electronic => 'moyasar',
            self::BankTransfer => null,
        };
    }

    /**
     * @return list<PaymentStatus>
     */
    private function nextStatesFrom(PaymentStatus $from): array
    {
        return match ($this) {
            self::Electronic => match ($from) {
                PaymentStatus::Created => [PaymentStatus::Pending, PaymentStatus::Cancelled],
                PaymentStatus::Pending => [PaymentStatus::Paid, PaymentStatus::Failed, PaymentStatus::Cancelled],
                PaymentStatus::Paid => [PaymentStatus::Refunded],
                default => [],
            },
            self::BankTransfer => match ($from) {
                PaymentStatus::UnderReview => [PaymentStatus::Paid, PaymentStatus::Rejected, PaymentStatus::Cancelled],
                PaymentStatus::Paid => [PaymentStatus::Refunded],
                default => [],
            },
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
