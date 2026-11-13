<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Periods\GeneratePeriods;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:periods:generate')]
final class GeneratePeriodsCommand extends Command
{
    protected $signature = 'moashir:periods:generate';

    protected $description = 'Create the accredited assessment periods up to today and the next four under the configured cadence; unstarted periods that no longer match are superseded. Safe to run again.';

    public function handle(GeneratePeriods $action): int
    {
        $superseded = $action->handle();
        $this->components->info(sprintf('Accredited periods are up to date (%d superseded).', count($superseded)));

        return self::SUCCESS;
    }
}
