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
        Schema::create('storage_measurements', function (Blueprint $table): void {
            $table->id();
            $table->date('measured_on')->unique();
            $table->unsignedBigInteger('used_bytes');
            $table->unsignedInteger('file_count');
            $table->timestampTz('measured_at')->index();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE storage_measurements ADD CONSTRAINT storage_measurements_used_bytes_check CHECK (used_bytes >= 0)');
        DB::statement('ALTER TABLE storage_measurements ADD CONSTRAINT storage_measurements_file_count_check CHECK (file_count >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_measurements');
    }
};
