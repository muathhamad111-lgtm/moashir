<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Entities\InstallRegionsSeed;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:regions:install-seed')]
final class InstallRegionsSeedCommand extends Command
{
    public const string SEED_PATH = 'data/regions/saudi-regions.json';

    protected $signature = 'moashir:regions:install-seed';

    protected $description = 'Install the 13 administrative regions and their main cities. Idempotent: adds only missing rows and never overwrites administrator edits.';

    public function handle(InstallRegionsSeed $installSeed): int
    {
        $result = $installSeed->handle(database_path(self::SEED_PATH));

        $this->components->info(sprintf('Regions added: %d. Cities added: %d.', $result->regionsAdded, $result->citiesAdded));

        return self::SUCCESS;
    }
}
