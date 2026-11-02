<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class GuardMarkerSeeder extends Seeder
{
    public const string TABLE = 'guard_marker';

    public function run(): void
    {
        DB::table(self::TABLE)->insert(['id' => 1]);
    }
}
