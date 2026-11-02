<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string SELF_STATES = "path = 'self' AND status IN ('draft', 'submitted', 'result_available')";

    private const string ACCREDITED_STATES = "path = 'accredited' AND status IN ('awaiting_payment', 'draft', 'submitted', 'assigned', 'under_review', 'needs_completion', 'completed', 'refunded', 'declined_incomplete')";

    private const string ADVANCED_STATES = "path = 'advanced' AND status IN ('rejected_conditions', 'draft', 'expired', 'awaiting_review_assignment', 'needs_completion', 'declined_incomplete', 'withdrawn', 'assigned', 'under_initial_assessment', 'awaiting_filtering', 'not_qualified', 'qualified_for_committee')";

    private const array PREVIOUS_CHECKS = [
        'assessments_path_check' => "path IN ('self', 'accredited')",
        'assessments_status_check' => '('.self::SELF_STATES.') OR ('.self::ACCREDITED_STATES.')',
        'assessments_draft_not_submitted_check' => "status NOT IN ('draft', 'awaiting_payment') OR submitted_at IS NULL",
        'assessments_submitted_at_check' => "(path = 'self' AND (status = 'draft' OR submitted_at IS NOT NULL)) OR (path = 'accredited' AND (status IN ('awaiting_payment', 'draft', 'refunded') OR submitted_at IS NOT NULL))",
        'assessments_review_sla_check' => "path <> 'accredited' OR (due_at IS NULL) = (review_sla_days IS NULL)",
    ];

    private const array NEW_CHECKS = [
        'assessments_cycle_check' => "(path = 'advanced') = (award_cycle_id IS NOT NULL)",
        'assessments_region_check' => "path <> 'advanced' OR region_id IS NOT NULL",
        'assessments_condition_failure_check' => "(status = 'rejected_conditions') = (condition_failure IS NOT NULL)",
        'assessments_withdrawn_check' => "(status = 'withdrawn') = (withdrawn_at IS NOT NULL)",
        'assessments_initial_result_check' => "path <> 'advanced' OR status NOT IN ('awaiting_filtering', 'not_qualified', 'qualified_for_committee') OR current_result_snapshot_id IS NOT NULL",
    ];

    private const array SNAPSHOT_KINDS = [
        'previous' => "kind IN ('self_result', 'accredited_result', 'override_recompute')",
        'current' => "kind IN ('self_result', 'accredited_result', 'override_recompute', 'initial_assessment')",
    ];

    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('award_cycle_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->jsonb('condition_failure')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
        });

        foreach ($this->currentChecks() as $name => $expression) {
            $this->replaceCheck('assessments', $name, $expression);
        }

        foreach (self::NEW_CHECKS as $name => $expression) {
            $this->replaceCheck('assessments', $name, $expression);
        }

        DB::statement("CREATE UNIQUE INDEX assessments_one_advanced_per_cycle ON assessments (entity_id, award_cycle_id) WHERE path = 'advanced' AND status <> 'rejected_conditions'");
        DB::statement("CREATE UNIQUE INDEX assessments_one_rejection_per_cycle ON assessments (entity_id, award_cycle_id) WHERE status = 'rejected_conditions'");
        DB::statement("CREATE INDEX assessments_advanced_cycle_status ON assessments (award_cycle_id, status) WHERE path = 'advanced'");
        $this->replaceCheck('assessment_result_snapshots', 'assessment_result_snapshots_kind_check', self::SNAPSHOT_KINDS['current']);
    }

    public function down(): void
    {
        $this->replaceCheck('assessment_result_snapshots', 'assessment_result_snapshots_kind_check', self::SNAPSHOT_KINDS['previous']);
        DB::statement('DROP INDEX IF EXISTS assessments_advanced_cycle_status');
        DB::statement('DROP INDEX IF EXISTS assessments_one_rejection_per_cycle');
        DB::statement('DROP INDEX IF EXISTS assessments_one_advanced_per_cycle');

        foreach (array_keys(self::NEW_CHECKS) as $name) {
            DB::statement(sprintf('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS %s', $name));
        }

        foreach (self::PREVIOUS_CHECKS as $name => $expression) {
            $this->replaceCheck('assessments', $name, $expression);
        }

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('award_cycle_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn(['condition_failure', 'withdrawn_at']);
        });
    }

    /**
     * @return array<string, string>
     */
    private function currentChecks(): array
    {
        return [
            'assessments_path_check' => "path IN ('self', 'accredited', 'advanced')",
            'assessments_status_check' => '('.self::SELF_STATES.') OR ('.self::ACCREDITED_STATES.') OR ('.self::ADVANCED_STATES.')',
            'assessments_draft_not_submitted_check' => "status NOT IN ('draft', 'awaiting_payment', 'rejected_conditions', 'expired') OR submitted_at IS NULL",
            'assessments_submitted_at_check' => "(path = 'self' AND (status = 'draft' OR submitted_at IS NOT NULL)) OR (path = 'accredited' AND (status IN ('awaiting_payment', 'draft', 'refunded') OR submitted_at IS NOT NULL)) OR (path = 'advanced' AND (status IN ('rejected_conditions', 'draft', 'expired') OR submitted_at IS NOT NULL))",
            'assessments_review_sla_check' => "path = 'self' OR (due_at IS NULL) = (review_sla_days IS NULL)",
        ];
    }

    private function replaceCheck(string $table, string $name, string $expression): void
    {
        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $expression));
    }
};
