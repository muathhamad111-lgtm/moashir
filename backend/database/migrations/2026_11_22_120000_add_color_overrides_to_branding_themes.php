<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The derived brand colours a client may set by hand (App\Enums\Settings\DerivedBrandColor).
    private const array KEYS = ['on_primary', 'primary_text', 'primary_hover', 'primary_soft', 'on_secondary'];

    public function up(): void
    {
        DB::statement("ALTER TABLE branding_themes ADD COLUMN color_overrides jsonb NOT NULL DEFAULT '{}'::jsonb");
        DB::statement(sprintf(
            "ALTER TABLE branding_themes ADD CONSTRAINT branding_themes_color_overrides_check CHECK (jsonb_typeof(color_overrides) = 'object' AND (color_overrides - ARRAY[%s]::text[]) = '{}'::jsonb AND NOT jsonb_path_exists(color_overrides, '$.* ? (@.type() != \"string\" || !(@ like_regex \"^#[0-9a-f]{6}$\"))'))",
            implode(', ', array_map(static fn (string $key): string => "'".$key."'", self::KEYS)),
        ));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE branding_themes DROP CONSTRAINT IF EXISTS branding_themes_color_overrides_check');
        DB::statement('ALTER TABLE branding_themes DROP COLUMN IF EXISTS color_overrides');
    }
};
