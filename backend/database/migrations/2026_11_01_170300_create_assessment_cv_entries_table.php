<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_cv_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('sub_code', 20);
            $table->text('body');
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['assessment_id', 'sub_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_cv_entries');
    }
};
