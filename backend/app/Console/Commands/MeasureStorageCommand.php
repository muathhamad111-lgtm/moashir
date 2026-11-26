<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Platform\MeasureStorage;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:storage:measure')]
final class MeasureStorageCommand extends Command
{
    protected $signature = 'moashir:storage:measure';

    protected $description = 'Record the bytes the platform files take on the files disk, once per day.';

    public function handle(MeasureStorage $measure): int
    {
        $measurement = $measure->handle();

        $this->components->info(sprintf('Measured %d file(s) taking %d bytes.', $measurement->file_count, $measurement->used_bytes));

        return self::SUCCESS;
    }
}
