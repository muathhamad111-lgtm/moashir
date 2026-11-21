<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string RETIRED_KEY = 'auth.entity_second_factor_enabled';

    public function up(): void
    {
        DB::table('settings')->where('key', self::RETIRED_KEY)->delete();
    }

    public function down(): void {}
};
