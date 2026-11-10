<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\RemindInactivePaidDrafts;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:assessments:remind-paid-drafts')]
final class RemindPaidDraftsCommand extends Command
{
    protected $signature = 'moashir:assessments:remind-paid-drafts';

    protected $description = 'Remind the entities of paid accredited drafts without activity for periodic.paid_draft_reminder_days (ACC-13), at most once per reminder window.';

    public function handle(RemindInactivePaidDrafts $action): int
    {
        $this->components->info(sprintf('Reminded %d paid drafts.', $action->handle()));

        return self::SUCCESS;
    }
}
