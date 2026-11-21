<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum TransferRejectionReason: string
{
    case NotReceived = 'not_received';
    case AmountMismatch = 'amount_mismatch';
    case Unreadable = 'unreadable';
    case WrongAccount = 'wrong_account';
    case Duplicate = 'duplicate';
    case Other = 'other';

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
