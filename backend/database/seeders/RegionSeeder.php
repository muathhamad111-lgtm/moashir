<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Entities\InstallRegionsSeed;
use App\Console\Commands\InstallRegionsSeedCommand;
use Illuminate\Database\Seeder;

final class RegionSeeder extends Seeder
{
    public function run(InstallRegionsSeed $installSeed): void
    {
        $installSeed->handle(database_path(InstallRegionsSeedCommand::SEED_PATH));
    }
}
