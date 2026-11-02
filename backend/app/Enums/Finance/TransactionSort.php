<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum TransactionSort: string
{
    case NewestFirst = '-created_at';
    case OldestFirst = 'created_at';
    case HighestTotal = '-total';
    case LowestTotal = 'total';
    case LatestPaid = '-paid_at';
    case EarliestPaid = 'paid_at';
    case LatestUpdate = '-updated_at';

    public function column(): string
    {
        return ltrim($this->value, '-');
    }

    /**
     * @return 'asc'|'desc'
     */
    public function direction(): string
    {
        return str_starts_with($this->value, '-') ? 'desc' : 'asc';
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
