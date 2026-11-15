<?php

declare(strict_types=1);

namespace App\Enums\Finance;

/**
 * The operations ledger splits transactions into three disjoint types: a refunded payment is a
 * refund, every other payment is typed by the channel it was paid through.
 */
enum TransactionType: string
{
    case Electronic = 'electronic';
    case BankTransfer = 'bank_transfer';
    case Refund = 'refund';

    public static function of(PaymentChannel $channel, PaymentStatus $status): self
    {
        if ($status === PaymentStatus::Refunded) {
            return self::Refund;
        }

        return match ($channel) {
            PaymentChannel::Electronic => self::Electronic,
            PaymentChannel::BankTransfer => self::BankTransfer,
        };
    }

    /**
     * Reads a filter list: unknown values are skipped and duplicates kept once, in first-seen order.
     *
     * @param  list<string>  $values
     * @return list<self>
     */
    public static function resolve(array $values): array
    {
        $types = [];

        foreach ($values as $value) {
            $type = self::tryFrom($value);

            if ($type instanceof self) {
                $types[$type->value] = $type;
            }
        }

        return array_values($types);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function channel(): ?PaymentChannel
    {
        return match ($this) {
            self::Electronic => PaymentChannel::Electronic,
            self::BankTransfer => PaymentChannel::BankTransfer,
            self::Refund => null,
        };
    }
}
