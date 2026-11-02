<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\ExpireCompletionRequests;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:assessments:expire-completion-requests')]
final class ExpireCompletionRequestsCommand extends Command
{
    protected $signature = 'moashir:assessments:expire-completion-requests';

    protected $description = 'Apply the expiry outcome chosen when completion was requested (continue as submitted or decline) to every accredited or advanced completion request past its deadline.';

    public function handle(ExpireCompletionRequests $action): int
    {
        $this->components->info(sprintf('Expired %d completion requests.', $action->handle()));

        return self::SUCCESS;
    }
}
