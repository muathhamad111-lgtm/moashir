<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string DEFAULT_PRIMARY = '#98d8d3';

    private const string DEFAULT_SECONDARY = '#0a2325';

    private const array PRIMARY_BY_ACCENT = [
        'emerald' => '#08704e',
        'teal' => '#0b7068',
        'cyan' => '#136f84',
        'sky' => '#1c5f9a',
        'lavender' => '#5146c9',
        'amber' => '#8a5a00',
        'coral' => '#a63d1f',
        'lime' => '#4d7314',
    ];

    public function up(): void
    {
        Schema::table('branding_themes', function (Blueprint $table): void {
            $table->char('primary_color', 7)->default(self::DEFAULT_PRIMARY);
            $table->char('secondary_color', 7)->default(self::DEFAULT_SECONDARY);
        });

        DB::statement("ALTER TABLE branding_themes ADD CONSTRAINT branding_themes_primary_color_check CHECK (primary_color ~ '^#[0-9a-f]{6}$')");
        DB::statement("ALTER TABLE branding_themes ADD CONSTRAINT branding_themes_secondary_color_check CHECK (secondary_color ~ '^#[0-9a-f]{6}$')");

        foreach (self::PRIMARY_BY_ACCENT as $accent => $primary) {
            DB::table('branding_themes')->where('accent_token', $accent)->update(['primary_color' => $primary]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE branding_themes DROP CONSTRAINT IF EXISTS branding_themes_primary_color_check');
        DB::statement('ALTER TABLE branding_themes DROP CONSTRAINT IF EXISTS branding_themes_secondary_color_check');

        Schema::table('branding_themes', function (Blueprint $table): void {
            $table->dropColumn(['primary_color', 'secondary_color']);
        });
    }
};
