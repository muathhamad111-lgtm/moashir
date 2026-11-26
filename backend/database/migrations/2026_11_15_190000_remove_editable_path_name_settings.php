<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Operator decision 2026-11-15: assessment path names are fixed translations and no longer editable,
 * so any name saved from «إدارة المسارات» is removed. Path descriptions stay editable.
 */
return new class extends Migration
{
    private const array RETIRED_KEYS = ['paths.self.name', 'paths.accredited.name', 'paths.advanced.name'];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::RETIRED_KEYS)->delete();
    }

    /**
     * Irreversible by design: the removed names are no longer read by the platform, so nothing is restored.
     */
    public function down(): void {}
};
