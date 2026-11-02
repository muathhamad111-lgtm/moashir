<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\OpenMissingAccreditedRequests;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:assessments:open-paid-requests')]
final class OpenMissingAccreditedRequestsCommand extends Command
{
    protected $signature = 'moashir:assessments:open-paid-requests';

    protected $description = 'Open the accredited request of every paid accredited payment whose request was not opened by the payment listener.';

    public function handle(OpenMissingAccreditedRequests $action): int
    {
        $this->components->info(sprintf('Opened %d accredited requests.', $action->handle()));

        return self::SUCCESS;
    }
}
