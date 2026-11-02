<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Criteria\InstallCriteriaSeed;
use App\Console\Commands\InstallCriteriaSeedCommand;
use Illuminate\Database\Seeder;

final class CriteriaSeeder extends Seeder
{
    public function run(InstallCriteriaSeed $installSeed): void
    {
        $installSeed->handle(database_path(InstallCriteriaSeedCommand::SEED_PATH));
    }
}
