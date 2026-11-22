<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentStatus;

/**
 * Operations per ledger status. "Awaiting payment" folds the created and pending states, as the list filter does.
 */
final readonly class StatusCounts
{
    public const string AWAITING_PAYMENT = 'awaiting_payment';

    /**
     * @param  array<string, int>  $counts  keyed by ledger status
     */
    private function __construct(private array $counts) {}

    /**
     * @param  array<string, int>  $byPaymentStatus  keyed by PaymentStatus value
     */
    public static function fromPaymentStatuses(array $byPaymentStatus): self
    {
        $counts = array_fill_keys(self::keys(), 0);

        foreach ($byPaymentStatus as $status => $count) {
            $counts[self::ledgerKeyOf($status)] += $count;
        }

        return new self($counts);
    }

    public function total(): int
    {
        return array_sum($this->counts);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->counts;
    }

    /**
     * @return list<string>
     */
    private static function keys(): array
    {
        return [
            PaymentStatus::Paid->value,
            PaymentStatus::UnderReview->value,
            self::AWAITING_PAYMENT,
            PaymentStatus::Rejected->value,
            PaymentStatus::Failed->value,
            PaymentStatus::Cancelled->value,
            PaymentStatus::Refunded->value,
        ];
    }

    private static function ledgerKeyOf(string $status): string
    {
        return in_array($status, [PaymentStatus::Created->value, PaymentStatus::Pending->value], true)
            ? self::AWAITING_PAYMENT
            : $status;
    }
}
