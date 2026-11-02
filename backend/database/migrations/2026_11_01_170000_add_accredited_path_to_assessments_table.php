<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string ACCREDITED_STATES = "'awaiting_payment', 'draft', 'submitted', 'assigned', 'under_review', 'needs_completion', 'completed', 'refunded'";

    private const string ACCREDITED_OPEN_STATES = "'awaiting_payment', 'draft', 'submitted', 'assigned', 'under_review', 'needs_completion'";

    private const array REPLACED_CHECKS = [
        'assessments_path_check' => "path IN ('self')",
        'assessments_status_check' => "(path = 'self' AND status IN ('draft', 'submitted', 'result_available'))",
        'assessments_draft_not_submitted_check' => "status <> 'draft' OR submitted_at IS NULL",
        'assessments_submitted_at_check' => "status = 'draft' OR submitted_at IS NOT NULL",
        'assessments_result_check' => "status <> 'result_available' OR (completed_at IS NOT NULL AND current_result_snapshot_id IS NOT NULL)",
        'assessments_criteria_update_hold_check' => 'criteria_update_pending_since IS NULL OR submitted_at IS NULL',
    ];

    private const array NEW_ONLY_CHECKS = ['assessments_payment_check', 'assessments_refunded_check', 'assessments_review_sla_check'];

    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->unsignedSmallInteger('review_sla_days')->nullable();
            $table->timestampTz('overdue_notified_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
        });

        foreach (array_keys(self::REPLACED_CHECKS) as $name) {
            DB::statement(sprintf('ALTER TABLE assessments DROP CONSTRAINT %s', $name));
        }

        foreach ($this->checks() as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessments ADD CONSTRAINT %s CHECK (%s)', $name, $expression));
        }

        DB::statement('CREATE UNIQUE INDEX assessments_payment_unique ON assessments (payment_id) WHERE payment_id IS NOT NULL');
        DB::statement(sprintf(
            "CREATE UNIQUE INDEX assessments_one_open_accredited ON assessments (entity_id) WHERE path = 'accredited' AND status IN (%s)",
            self::ACCREDITED_OPEN_STATES,
        ));
        DB::statement(sprintf(
            "CREATE INDEX assessments_open_due_at ON assessments (due_at) WHERE path = 'accredited' AND status IN (%s)",
            self::ACCREDITED_OPEN_STATES,
        ));
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS assessments_open_due_at');
        DB::statement('DROP INDEX IF EXISTS assessments_one_open_accredited');
        DB::statement('DROP INDEX IF EXISTS assessments_payment_unique');

        foreach ([...array_keys(self::REPLACED_CHECKS), ...self::NEW_ONLY_CHECKS] as $name) {
            DB::statement(sprintf('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS %s', $name));
        }

        foreach (self::REPLACED_CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessments ADD CONSTRAINT %s CHECK (%s)', $name, $expression));
        }

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn(['review_sla_days', 'overdue_notified_at', 'refunded_at']);
        });
    }

    /**
     * @return array<string, string>
     */
    private function checks(): array
    {
        return [
            'assessments_path_check' => "path IN ('self', 'accredited')",
            'assessments_status_check' => sprintf(
                "(path = 'self' AND status IN ('draft', 'submitted', 'result_available')) OR (path = 'accredited' AND status IN (%s))",
                self::ACCREDITED_STATES,
            ),
            'assessments_draft_not_submitted_check' => "status NOT IN ('draft', 'awaiting_payment') OR submitted_at IS NULL",
            'assessments_submitted_at_check' => "(path = 'self' AND (status = 'draft' OR submitted_at IS NOT NULL)) OR (path = 'accredited' AND (status IN ('awaiting_payment', 'draft', 'refunded') OR submitted_at IS NOT NULL))",
            'assessments_result_check' => "status NOT IN ('result_available', 'completed') OR (completed_at IS NOT NULL AND current_result_snapshot_id IS NOT NULL)",
            'assessments_criteria_update_hold_check' => "criteria_update_pending_since IS NULL OR (path = 'self' AND submitted_at IS NULL) OR (path = 'accredited' AND status IN ('draft', 'submitted', 'assigned', 'under_review', 'needs_completion'))",
            'assessments_payment_check' => "(path = 'accredited') = (payment_id IS NOT NULL)",
            'assessments_refunded_check' => "status <> 'refunded' OR refunded_at IS NOT NULL",
            'assessments_review_sla_check' => "path <> 'accredited' OR (due_at IS NULL) = (review_sla_days IS NULL)",
        ];
    }
};
