<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_renditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_file_id')->unique()->constrained('stored_files')->cascadeOnDelete();
            $table->foreignId('rendition_file_id')->nullable()->index()->constrained('stored_files')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->string('failure_code', 60)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_renditions');
    }
};
