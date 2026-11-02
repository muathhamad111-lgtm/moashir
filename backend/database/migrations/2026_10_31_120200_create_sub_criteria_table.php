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
        Schema::create('sub_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('main_criterion_id')->index()->constrained('main_criteria')->cascadeOnDelete();
            $table->string('code', 20);
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->decimal('weight', 10, 2);
            $table->boolean('distribute_equally')->default(false);
            $table->string('evaluation_type', 20)->default('guiding_points');
            $table->boolean('has_cv')->default(true);
            $table->jsonb('assessor_note_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order');
            $table->foreignId('cloned_from_id')->nullable()->index()->constrained('sub_criteria')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['main_criterion_id', 'code']);
        });

        DB::statement('ALTER TABLE sub_criteria ADD CONSTRAINT sub_criteria_weight_check CHECK (weight > 0)');
        DB::statement("ALTER TABLE sub_criteria ADD CONSTRAINT sub_criteria_evaluation_type_check CHECK (evaluation_type IN ('direct', 'guiding_points'))");
        DB::statement("ALTER TABLE sub_criteria ADD CONSTRAINT sub_criteria_name_check CHECK (jsonb_typeof(name) = 'object')");
        DB::statement("ALTER TABLE sub_criteria ADD CONSTRAINT sub_criteria_description_check CHECK (description IS NULL OR jsonb_typeof(description) = 'object')");
        DB::statement("ALTER TABLE sub_criteria ADD CONSTRAINT sub_criteria_note_label_check CHECK (assessor_note_label IS NULL OR jsonb_typeof(assessor_note_label) = 'object')");
        DB::statement('ALTER TABLE sub_criteria ADD CONSTRAINT sub_criteria_sort_order_unique UNIQUE (main_criterion_id, sort_order) DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_criteria');
    }
};
