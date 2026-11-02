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
        Schema::create('attachment_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sub_criterion_id')->nullable()->index()->constrained('sub_criteria')->cascadeOnDelete();
            $table->foreignId('guiding_point_id')->nullable()->index()->constrained('guiding_points')->cascadeOnDelete();
            $table->string('key', 40);
            $table->jsonb('name');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE attachment_requirements ADD CONSTRAINT attachment_requirements_one_owner_check CHECK (num_nonnulls(sub_criterion_id, guiding_point_id) = 1)');
        DB::statement("ALTER TABLE attachment_requirements ADD CONSTRAINT attachment_requirements_name_check CHECK (jsonb_typeof(name) = 'object')");
        DB::statement('CREATE UNIQUE INDEX attachment_requirements_sub_key_unique ON attachment_requirements (sub_criterion_id, key) WHERE sub_criterion_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX attachment_requirements_point_key_unique ON attachment_requirements (guiding_point_id, key) WHERE guiding_point_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_requirements');
    }
};
