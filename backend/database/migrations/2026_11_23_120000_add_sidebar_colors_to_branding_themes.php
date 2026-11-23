<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The two colours of the sidebar gradient; NULL keeps the colour derived from the brand colours.
    private const array COLUMNS = ['sidebar_top_color', 'sidebar_bottom_color'];

    public function up(): void
    {
        Schema::table('branding_themes', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->char($column, 7)->nullable();
            }
        });

        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE branding_themes ADD CONSTRAINT branding_themes_{$column}_check CHECK ({$column} IS NULL OR {$column} ~ '^#[0-9a-f]{6}$')");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE branding_themes DROP CONSTRAINT IF EXISTS branding_themes_{$column}_check");
        }

        Schema::table('branding_themes', function (Blueprint $table): void {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
