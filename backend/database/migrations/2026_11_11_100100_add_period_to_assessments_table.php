<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string PERIOD_CHECK = "period_id IS NULL OR (path = 'accredited' AND submitted_at IS NOT NULL AND period_assigned_at IS NOT NULL)";

    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('period_id')->nullable()->constrained('assessment_periods')->restrictOnDelete();
            $table->timestampTz('period_assigned_at')->nullable();
            $table->timestampTz('paid_draft_reminded_at')->nullable();

            $table->index(['entity_id', 'period_id']);
        });

        DB::statement(sprintf('ALTER TABLE assessments ADD CONSTRAINT assessments_period_check CHECK (%s)', self::PERIOD_CHECK));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_period_check');

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropIndex(['entity_id', 'period_id']);
            $table->dropConstrainedForeignId('period_id');
            $table->dropColumn(['period_assigned_at', 'paid_draft_reminded_at']);
        });
    }
};
