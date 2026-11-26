<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array ACCENT_TOKENS = ['brand_default', 'emerald', 'teal', 'cyan', 'sky', 'lavender', 'amber', 'coral', 'lime'];

    public function up(): void
    {
        Schema::create('branding_themes', function (Blueprint $table): void {
            $table->smallInteger('id')->primary();
            $table->string('accent_token', 30)->default('brand_default');
            $table->foreignId('logo_light_file_id')->nullable()->index()->constrained('stored_files')->nullOnDelete();
            $table->foreignId('logo_dark_file_id')->nullable()->index()->constrained('stored_files')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE branding_themes ADD CONSTRAINT branding_themes_singleton_check CHECK (id = 1)');
        DB::statement(sprintf(
            'ALTER TABLE branding_themes ADD CONSTRAINT branding_themes_accent_token_check CHECK (accent_token IN (%s))',
            implode(', ', array_map(static fn (string $token): string => "'".$token."'", self::ACCENT_TOKENS)),
        ));

        DB::table('branding_themes')->insert(['id' => 1, 'accent_token' => 'brand_default', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_themes');
    }
};
