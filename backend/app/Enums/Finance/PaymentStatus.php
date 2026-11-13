<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Paid = 'paid';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    private const array OPEN = [self::Created, self::Pending, self::UnderReview];

    public function isOpen(): bool
    {
        return in_array($this, self::OPEN, true);
    }

    public function isConcluded(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Rejected, self::Refunded], true);
    }

    public function hasBeenPaid(): bool
    {
        return in_array($this, [self::Paid, self::Refunded], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::OPEN);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
