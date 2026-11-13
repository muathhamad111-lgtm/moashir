<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Periods\CloseEndedPeriods;
use App\Actions\Periods\SettleInProgressPeriods;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:periods:close')]
final class ClosePeriodsCommand extends Command
{
    protected $signature = 'moashir:periods:close';

    protected $description = 'Record the entity status of every ended accredited period (assessed, missed, closed without result, frozen, not required), notify missed entities (PER-03) and settle late results. Safe to run again.';

    public function handle(CloseEndedPeriods $close, SettleInProgressPeriods $settle): int
    {
        $closed = $close->handle();
        $settled = $settle->handle();
        $this->components->info(sprintf('Closed %d periods and settled %d in-progress statuses.', $closed, $settled));

        return self::SUCCESS;
    }
}
