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

    private const string PART_ONE_STATES = "'rejected_conditions', 'draft', 'expired', 'awaiting_review_assignment', 'needs_completion', 'declined_incomplete', 'withdrawn', 'assigned', 'under_initial_assessment', 'awaiting_filtering', 'not_qualified', 'qualified_for_committee'";

    private const string COMMITTEE_STATES = "'under_committee_assessment', 'committee_discussion', 'awaiting_secretary_approval', 'returned_to_committee', 'approved', 'regional_winner', 'non_winner', 'qualified_for_national', 'national_winner', 'national_non_winner'";

    private const string PART_ONE_RESULT_STATES = "'awaiting_filtering', 'not_qualified', 'qualified_for_committee'";

    public function up(): void
    {
        $this->replaceCheck('assessments', 'assessments_status_check', $this->statusCheck(self::PART_ONE_STATES.', '.self::COMMITTEE_STATES));
        $this->replaceCheck('assessments', 'assessments_initial_result_check', $this->resultCheck(self::PART_ONE_RESULT_STATES.', '.self::COMMITTEE_STATES));

        Schema::table('award_cycles', function (Blueprint $table): void {
            $table->timestampTz('committee_started_at')->nullable();
            $table->timestampTz('national_approved_at')->nullable();
            $table->foreignId('national_approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('announced_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });

        $this->replaceCheck('award_cycles', 'award_cycles_announced_check', "status <> 'announced' OR (announced_at IS NOT NULL AND national_approved_at IS NOT NULL)");
        $this->replaceCheck('award_cycles', 'award_cycles_national_check', "status <> 'approved' OR national_approved_at IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE award_cycles DROP CONSTRAINT IF EXISTS award_cycles_national_check');
        DB::statement('ALTER TABLE award_cycles DROP CONSTRAINT IF EXISTS award_cycles_announced_check');

        Schema::table('award_cycles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('announced_by_user_id');
            $table->dropConstrainedForeignId('national_approved_by_user_id');
            $table->dropColumn(['committee_started_at', 'national_approved_at']);
        });

        $this->replaceCheck('assessments', 'assessments_initial_result_check', $this->resultCheck(self::PART_ONE_RESULT_STATES));
        $this->replaceCheck('assessments', 'assessments_status_check', $this->statusCheck(self::PART_ONE_STATES));
    }

    private function statusCheck(string $advancedStates): string
    {
        return '('.self::SELF_STATES.') OR ('.self::ACCREDITED_STATES.") OR (path = 'advanced' AND status IN (".$advancedStates.'))';
    }

    private function resultCheck(string $states): string
    {
        return "path <> 'advanced' OR status NOT IN (".$states.') OR current_result_snapshot_id IS NOT NULL';
    }

    private function replaceCheck(string $table, string $name, string $expression): void
    {
        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $expression));
    }
};
