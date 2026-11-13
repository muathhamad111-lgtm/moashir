<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class FinanceSummary
{
    /**
     * @param  array<string, FinanceStats>  $byPurpose
     * @param  array<string, FinanceStats>  $byChannel
     */
    public function __construct(public FinanceStats $overall, public array $byPurpose, public array $byChannel) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $purposes = [];

        foreach ($this->byPurpose as $purpose => $stats) {
            $purposes[] = ['purpose' => $purpose, ...$stats->toArray()];
        }

        $channels = [];

        foreach ($this->byChannel as $channel => $stats) {
            $channels[] = ['channel' => $channel, ...$stats->toArray()];
        }

        return [...$this->overall->toArray(), 'by_purpose' => $purposes, 'by_channel' => $channels];
    }
}
