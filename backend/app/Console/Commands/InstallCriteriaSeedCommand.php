<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Criteria\InstallCriteriaSeed;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:criteria:install-seed')]
final class InstallCriteriaSeedCommand extends Command
{
    public const string SEED_PATH = 'data/criteria/version-1.json';

    protected $signature = 'moashir:criteria:install-seed';

    protected $description = 'Install and publish the bundled criteria version 1 when no criteria version exists yet. Idempotent.';

    public function handle(InstallCriteriaSeed $installSeed): int
    {
        $version = $installSeed->handle(database_path(self::SEED_PATH));

        if (! $version instanceof CriteriaVersion) {
            $this->components->info('Skipped: a criteria version already exists.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Criteria version %d was installed and published with %d main criteria.',
            $version->version_number,
            $version->mainCriteria->count(),
        ));

        return self::SUCCESS;
    }
}
