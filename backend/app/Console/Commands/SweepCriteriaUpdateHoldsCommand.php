<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\SweepStaleCriteriaUpdateHolds;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:assessments:sweep-criteria-update-holds')]
final class SweepCriteriaUpdateHoldsCommand extends Command
{
    protected $signature = 'moashir:assessments:sweep-criteria-update-holds';

    protected $description = 'Re-dispatch the criteria update of restored entities whose drafts have been held for longer than the configured time.';

    public function handle(SweepStaleCriteriaUpdateHolds $sweep): int
    {
        $this->components->info(sprintf('Re-dispatched the criteria update of %d entities.', $sweep->handle()));

        return self::SUCCESS;
    }
}
