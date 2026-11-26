<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Files\QueueOfficeRenditions;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:files:build-renditions')]
final class QueueOfficeRenditionsCommand extends Command
{
    protected $signature = 'moashir:files:build-renditions';

    protected $description = 'Queue the PDF conversion of clean Word, Excel and PowerPoint attachments that have no PDF rendition yet.';

    public function handle(QueueOfficeRenditions $queue): int
    {
        $this->components->info(sprintf('Queued the PDF conversion of %d file(s).', $queue->handle()));

        return self::SUCCESS;
    }
}
