<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Files\PurgeOrphanFiles;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:files:purge-orphans')]
final class PurgeOrphanFilesCommand extends Command
{
    protected $signature = 'moashir:files:purge-orphans';

    protected $description = 'Remove stored files that were never attached to an owner within the grace period.';

    public function handle(PurgeOrphanFiles $purge): int
    {
        $counts = $purge->handle()->counts;

        $this->components->info(sprintf(
            'Purged %d orphan file(s) (%d bytes) older than %d hours.',
            ScalarValue::int($counts['purged_files'] ?? null),
            ScalarValue::int($counts['purged_bytes'] ?? null),
            ScalarValue::int($counts['grace_hours'] ?? null),
        ));

        return self::SUCCESS;
    }
}
