<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Award\FlagOverdueCommitteeAssessments;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:award:flag-overdue-committee')]
final class FlagOverdueCommitteeCommand extends Command
{
    protected $signature = 'moashir:award:flag-overdue-committee';

    protected $description = 'Alert committee assessors, award secretaries and System Administrators once about committee evaluations still open after the committee end date.';

    public function handle(FlagOverdueCommitteeAssessments $action): int
    {
        $this->components->info(sprintf('Flagged %d overdue committee evaluations.', $action->handle()));

        return self::SUCCESS;
    }
}
