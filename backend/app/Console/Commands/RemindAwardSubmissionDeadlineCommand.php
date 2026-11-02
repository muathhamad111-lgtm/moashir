<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Award\SendSubmissionDeadlineReminders;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:award:remind-submission-deadline')]
final class RemindAwardSubmissionDeadlineCommand extends Command
{
    protected $signature = 'moashir:award:remind-submission-deadline';

    protected $description = 'Remind the entities with an unsubmitted award draft 7, 3 and 1 days before the submission end of the open cycle.';

    public function handle(SendSubmissionDeadlineReminders $action): int
    {
        $this->components->info(sprintf('Reminded %d award drafts.', $action->handle()));

        return self::SUCCESS;
    }
}
