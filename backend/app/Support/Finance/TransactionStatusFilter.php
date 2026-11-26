<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\Finance\PaymentStatus;

/**
 * The status vocabulary of the operations ledger filter: every payment status, plus the
 * grouped alias `awaiting_payment` (an electronic order not paid yet: created or pending).
 */
final class TransactionStatusFilter
{
    public const string AWAITING_PAYMENT = 'awaiting_payment';

    private const array AWAITING_PAYMENT_STATUSES = [PaymentStatus::Created, PaymentStatus::Pending];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [...PaymentStatus::values(), self::AWAITING_PAYMENT];
    }

    /**
     * @param  list<string>  $values
     * @return list<PaymentStatus>
     */
    public static function resolve(array $values): array
    {
        $statuses = [];

        foreach ($values as $value) {
            foreach (self::expand($value) as $status) {
                $statuses[$status->value] = $status;
            }
        }

        return array_values($statuses);
    }

    /**
     * @return list<PaymentStatus>
     */
    private static function expand(string $value): array
    {
        if ($value === self::AWAITING_PAYMENT) {
            return self::AWAITING_PAYMENT_STATUSES;
        }

        $status = PaymentStatus::tryFrom($value);

        return $status instanceof PaymentStatus ? [$status] : [];
    }
}
