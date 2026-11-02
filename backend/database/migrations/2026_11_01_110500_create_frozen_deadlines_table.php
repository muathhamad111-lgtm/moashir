<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frozen_deadlines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->index()->constrained()->restrictOnDelete();
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->string('attribute', 60);
            $table->timestampTz('due_at');
            $table->bigInteger('remaining_seconds');
            $table->timestampTz('frozen_at');
            $table->timestampTz('created_at');

            $table->unique(['subject_type', 'subject_id', 'attribute']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frozen_deadlines');
    }
};
