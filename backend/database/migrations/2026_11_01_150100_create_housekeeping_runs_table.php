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
        Schema::create('housekeeping_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('task', 40);
            $table->jsonb('counts');
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at');
            $table->timestampsTz();

            $table->index(['task', 'started_at']);
        });

        DB::statement("ALTER TABLE housekeeping_runs ADD CONSTRAINT housekeeping_runs_task_check CHECK (task IN ('orphan_file_purge'))");
        DB::statement('ALTER TABLE housekeeping_runs ADD CONSTRAINT housekeeping_runs_period_check CHECK (completed_at >= started_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_runs');
    }
};
