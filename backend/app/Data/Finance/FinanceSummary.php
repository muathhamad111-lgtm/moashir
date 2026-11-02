<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class FinanceSummary
{
    /**
     * @param  array<string, FinanceStats>  $byPurpose
     */
    public function __construct(public FinanceStats $overall, public array $byPurpose) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $purposes = [];

        foreach ($this->byPurpose as $purpose => $stats) {
            $purposes[] = ['purpose' => $purpose, ...$stats->toArray()];
        }

        return [...$this->overall->toArray(), 'by_purpose' => $purposes];
    }
}
