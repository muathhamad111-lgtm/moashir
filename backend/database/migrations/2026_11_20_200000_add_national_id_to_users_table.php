<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string ACTIVE_UNIQUE = 'users_active_national_id_index_unique';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('national_id')->nullable();
            $table->char('national_id_index', 64)->nullable();
        });

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON users (national_id_index) WHERE national_id_index IS NOT NULL AND deleted_at IS NULL',
            self::ACTIVE_UNIQUE,
        ));
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::ACTIVE_UNIQUE));

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['national_id', 'national_id_index']);
        });
    }
};
