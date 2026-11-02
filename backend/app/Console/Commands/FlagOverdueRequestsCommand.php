<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\FlagOverdueRequests;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:assessments:flag-overdue')]
final class FlagOverdueRequestsCommand extends Command
{
    protected $signature = 'moashir:assessments:flag-overdue';

    protected $description = 'Notify the assessor, the Requests Followers and the System Administrators once about every accredited request past its review due date.';

    public function handle(FlagOverdueRequests $action): int
    {
        $this->components->info(sprintf('Flagged %d overdue requests.', $action->handle()));

        return self::SUCCESS;
    }
}
