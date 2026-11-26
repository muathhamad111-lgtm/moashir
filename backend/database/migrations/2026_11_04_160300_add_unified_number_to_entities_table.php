<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string UNIQUE_INDEX = 'entities_unified_number_unique';

    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->string('unified_number', 10)->nullable();
        });

        DB::statement("ALTER TABLE entities ADD CONSTRAINT entities_unified_number_check CHECK (unified_number IS NULL OR unified_number ~ '^7[0-9]{9}$')");
        DB::statement(sprintf('CREATE UNIQUE INDEX %s ON entities (unified_number) WHERE unified_number IS NOT NULL', self::UNIQUE_INDEX));
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::UNIQUE_INDEX));
        DB::statement('ALTER TABLE entities DROP CONSTRAINT IF EXISTS entities_unified_number_check');

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropColumn('unified_number');
        });
    }
};
