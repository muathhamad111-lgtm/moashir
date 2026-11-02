<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionCatalogSeeder::class);
        $this->call(BreachedPasswordSeeder::class);
        $this->call(CriteriaSeeder::class);
        $this->call(RegionSeeder::class);

        if (app()->environment('local')) {
            $this->call(LocalStaffSeeder::class);
            $this->call(LocalTermsSeeder::class);
        }
    }
}
