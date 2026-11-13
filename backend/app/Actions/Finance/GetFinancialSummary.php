<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\FinanceSummary;
use App\Data\Finance\TransactionFilters;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Services\Finance\FinanceStatsCalculator;
use App\Services\Finance\TransactionQuery;
use Carbon\CarbonImmutable;

final readonly class GetFinancialSummary
{
    public function __construct(private TransactionQuery $query, private FinanceStatsCalculator $stats) {}

    public function handle(?CarbonImmutable $from, ?CarbonImmutable $to): FinanceSummary
    {
        $byPurpose = [];

        foreach (PaymentPurpose::cases() as $purpose) {
            $byPurpose[$purpose->value] = $this->stats->overall($this->query->filtered(new TransactionFilters(purpose: $purpose, from: $from, to: $to)));
        }

        $byChannel = [];

        foreach (PaymentChannel::cases() as $channel) {
            $byChannel[$channel->value] = $this->stats->overall($this->query->filtered(new TransactionFilters(from: $from, to: $to, channel: $channel)));
        }

        return new FinanceSummary($this->stats->overall($this->query->filtered(new TransactionFilters(from: $from, to: $to))), $byPurpose, $byChannel);
    }
}
