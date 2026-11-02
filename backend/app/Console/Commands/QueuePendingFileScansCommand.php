<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Files\QueuePendingFileScans;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:files:scan-pending')]
final class QueuePendingFileScansCommand extends Command
{
    protected $signature = 'moashir:files:scan-pending';

    protected $description = 'Queue a background malware scan for stored files that are still awaiting their scan.';

    public function handle(QueuePendingFileScans $queue): int
    {
        $this->components->info(sprintf('Queued the malware scan of %d file(s).', $queue->handle()));

        return self::SUCCESS;
    }
}
