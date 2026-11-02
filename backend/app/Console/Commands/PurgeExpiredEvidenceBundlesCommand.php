<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\PurgeExpiredEvidenceBundles;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:assessments:purge-evidence-bundles')]
final class PurgeExpiredEvidenceBundlesCommand extends Command
{
    protected $signature = 'moashir:assessments:purge-evidence-bundles';

    protected $description = 'Remove the files of evidence bundles whose download window has ended.';

    public function handle(PurgeExpiredEvidenceBundles $action): int
    {
        $this->components->info(sprintf('Purged %d expired evidence bundles.', $action->handle()));

        return self::SUCCESS;
    }
}
