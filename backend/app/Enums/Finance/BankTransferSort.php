<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum BankTransferSort: string
{
    case OldestFirst = 'submitted_at';
    case NewestFirst = '-submitted_at';

    /**
     * @return 'asc'|'desc'
     */
    public function direction(): string
    {
        return $this === self::NewestFirst ? 'desc' : 'asc';
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
