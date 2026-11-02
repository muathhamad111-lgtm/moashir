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
        Schema::create('guiding_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sub_criterion_id')->index()->constrained('sub_criteria')->cascadeOnDelete();
            $table->string('code', 20);
            $table->jsonb('description');
            $table->decimal('weight', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order');
            $table->foreignId('cloned_from_id')->nullable()->index()->constrained('guiding_points')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['sub_criterion_id', 'code']);
        });

        DB::statement('ALTER TABLE guiding_points ADD CONSTRAINT guiding_points_weight_check CHECK (weight > 0)');
        DB::statement("ALTER TABLE guiding_points ADD CONSTRAINT guiding_points_description_check CHECK (jsonb_typeof(description) = 'object')");
        DB::statement('ALTER TABLE guiding_points ADD CONSTRAINT guiding_points_sort_order_unique UNIQUE (sub_criterion_id, sort_order) DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('guiding_points');
    }
};
