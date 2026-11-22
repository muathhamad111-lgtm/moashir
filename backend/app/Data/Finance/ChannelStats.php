<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Support\ValueObjects\Money;

final readonly class ChannelStats
{
    public function __construct(public StatusCounts $statusCounts, public Money $paidTotal) {}

    /**
     * @return array{count: int, paid_total: string, status_counts: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'count' => $this->statusCounts->total(),
            'paid_total' => $this->paidTotal->amount,
            'status_counts' => $this->statusCounts->toArray(),
        ];
    }
}
