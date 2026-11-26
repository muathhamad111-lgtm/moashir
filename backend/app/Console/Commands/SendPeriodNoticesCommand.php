<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Periods\SendPeriodNotices;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:periods:notify')]
final class SendPeriodNoticesCommand extends Command
{
    protected $signature = 'moashir:periods:notify';

    protected $description = 'Send the accredited period notices that are due: PER-01 before a period starts and PER-02 before it ends. Each entity receives each notice once.';

    public function handle(SendPeriodNotices $action): int
    {
        $this->components->info(sprintf('Sent %d period notices.', $action->handle()));

        return self::SUCCESS;
    }
}
