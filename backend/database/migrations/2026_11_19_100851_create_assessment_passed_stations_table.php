<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stations (main criteria) an entity has moved past in its reviewed submission form. A
 * sub-criterion whose evidence is all optional reads as complete only once its station is passed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_passed_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('main_criterion_id')->index()->constrained('main_criteria')->cascadeOnDelete();
            $table->timestampTz('passed_at');
            $table->unique(['assessment_id', 'main_criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_passed_stations');
    }
};
