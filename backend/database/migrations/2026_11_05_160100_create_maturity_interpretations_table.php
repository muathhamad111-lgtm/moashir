<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array BANDS = ['emerging', 'developing', 'mature', 'leading'];

    public function up(): void
    {
        Schema::create('maturity_interpretations', function (Blueprint $table): void {
            $table->id();
            $table->string('band', 20);
            $table->string('main_criterion_code', 20)->nullable();
            $table->jsonb('text')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement(sprintf(
            "ALTER TABLE maturity_interpretations ADD CONSTRAINT maturity_interpretations_band_check CHECK (band IN ('%s'))",
            implode("', '", self::BANDS),
        ));
        DB::statement('ALTER TABLE maturity_interpretations ADD CONSTRAINT maturity_interpretations_override_text_check CHECK (main_criterion_code IS NULL OR text IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX maturity_interpretations_generic ON maturity_interpretations (band) WHERE main_criterion_code IS NULL');
        DB::statement('CREATE UNIQUE INDEX maturity_interpretations_override ON maturity_interpretations (band, main_criterion_code) WHERE main_criterion_code IS NOT NULL');

        $now = now();
        DB::table('maturity_interpretations')->insert(array_map(static fn (string $band): array => [
            'band' => $band,
            'main_criterion_code' => null,
            'text' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::BANDS));
    }

    public function down(): void
    {
        Schema::dropIfExists('maturity_interpretations');
    }
};
