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
        Schema::create('main_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('criteria_version_id')->index()->constrained('criteria_versions')->cascadeOnDelete();
            $table->string('code', 20);
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->decimal('weight', 10, 2);
            $table->boolean('distribute_equally')->default(false);
            $table->string('group', 25)->default('enablers');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order');
            $table->foreignId('cloned_from_id')->nullable()->index()->constrained('main_criteria')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['criteria_version_id', 'code']);
        });

        DB::statement('ALTER TABLE main_criteria ADD CONSTRAINT main_criteria_weight_check CHECK (weight > 0)');
        DB::statement("ALTER TABLE main_criteria ADD CONSTRAINT main_criteria_group_check CHECK (\"group\" IN ('enablers', 'creativity_learning', 'results'))");
        DB::statement("ALTER TABLE main_criteria ADD CONSTRAINT main_criteria_name_check CHECK (jsonb_typeof(name) = 'object')");
        DB::statement("ALTER TABLE main_criteria ADD CONSTRAINT main_criteria_description_check CHECK (description IS NULL OR jsonb_typeof(description) = 'object')");
        DB::statement('ALTER TABLE main_criteria ADD CONSTRAINT main_criteria_sort_order_unique UNIQUE (criteria_version_id, sort_order) DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('main_criteria');
    }
};
