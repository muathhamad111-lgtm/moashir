<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->jsonb('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->jsonb('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['region_id', 'code']);
            $table->unique(['id', 'region_id']);
            $table->index(['region_id', 'sort_order']);
        });

        foreach (['regions', 'cities'] as $table) {
            DB::statement(sprintf(
                "ALTER TABLE %1\$s ADD CONSTRAINT %1\$s_name_check CHECK (jsonb_typeof(name) = 'object' AND coalesce(name->>'ar', '') <> '' AND coalesce(name->>'en', '') <> '')",
                $table,
            ));
            DB::statement(sprintf("ALTER TABLE %1\$s ADD CONSTRAINT %1\$s_code_check CHECK (code ~ '^[a-z0-9_]+$')", $table));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
    }
};
