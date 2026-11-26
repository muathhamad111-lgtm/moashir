<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class FinanceBreakdown
{
    /**
     * @param  array<string, ChannelStats>  $channels  keyed by PaymentChannel value
     */
    public function __construct(public StatusCounts $statusCounts, public array $channels) {}

    /**
     * @return array{status_counts: array<string, int>, channels: array<string, array{count: int, paid_total: string, status_counts: array<string, int>}>}
     */
    public function toArray(): array
    {
        return [
            'status_counts' => $this->statusCounts->toArray(),
            'channels' => array_map(static fn (ChannelStats $channel): array => $channel->toArray(), $this->channels),
        ];
    }
}
