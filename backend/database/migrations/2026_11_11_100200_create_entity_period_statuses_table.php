<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string STATUS_CHECK = "status IN ('assessed', 'in_progress', 'missed', 'closed_without_result', 'frozen', 'not_required')";

    public function up(): void
    {
        Schema::create('entity_period_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_period_id')->index()->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('decided_at');
            $table->timestampsTz();

            $table->unique(['entity_id', 'assessment_period_id']);
            $table->index(['entity_id', 'status']);
        });

        DB::statement(sprintf('ALTER TABLE entity_period_statuses ADD CONSTRAINT entity_period_statuses_status_check CHECK (%s)', self::STATUS_CHECK));
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_period_statuses');
    }
};
