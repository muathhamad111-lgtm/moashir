<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function allowsTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextStates(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Created, self::Pending], true);
    }

    public function isConcluded(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Refunded], true);
    }

    public function hasBeenPaid(): bool
    {
        return in_array($this, [self::Paid, self::Refunded], true);
    }

    /**
     * @return list<self>
     */
    private function nextStates(): array
    {
        return match ($this) {
            self::Created => [self::Pending, self::Cancelled],
            self::Pending => [self::Paid, self::Failed, self::Cancelled],
            self::Paid => [self::Refunded],
            self::Failed, self::Cancelled, self::Refunded => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Created->value, self::Pending->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
