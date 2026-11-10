<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string ACCREDITED_STATES = "path = 'accredited' AND status IN ('awaiting_payment', 'draft', 'submitted', 'assigned', 'under_review', 'needs_completion', 'completed', 'refunded', 'declined_incomplete')";

    private const string ADVANCED_STATES = "'rejected_conditions', 'draft', 'expired', 'awaiting_review_assignment', 'needs_completion', 'declined_incomplete', 'withdrawn', 'assigned', 'under_initial_assessment', 'awaiting_filtering', 'not_qualified', 'qualified_for_committee', 'under_committee_assessment', 'committee_discussion', 'awaiting_secretary_approval', 'returned_to_committee', 'approved', 'regional_winner', 'non_winner', 'qualified_for_national', 'national_winner', 'national_non_winner'";

    private const array PREVIOUS = [
        'status' => "(path = 'self' AND status IN ('draft', 'submitted', 'result_available')) OR (".self::ACCREDITED_STATES.") OR (path = 'advanced' AND status IN (".self::ADVANCED_STATES.'))',
        'submitted_at' => "(path = 'self' AND (status = 'draft' OR submitted_at IS NOT NULL)) OR (path = 'accredited' AND (status IN ('awaiting_payment', 'draft', 'refunded') OR submitted_at IS NOT NULL)) OR (path = 'advanced' AND (status IN ('rejected_conditions', 'draft', 'expired') OR submitted_at IS NOT NULL))",
        'payment' => "(path = 'accredited') = (payment_id IS NOT NULL)",
    ];

    private const array CURRENT = [
        'status' => "(path = 'self' AND status IN ('awaiting_payment', 'draft', 'submitted', 'result_available', 'refunded')) OR (".self::ACCREDITED_STATES.") OR (path = 'advanced' AND status IN (".self::ADVANCED_STATES.", 'refunded'))",
        'submitted_at' => "(path = 'self' AND (status IN ('awaiting_payment', 'draft', 'refunded') OR submitted_at IS NOT NULL)) OR (path = 'accredited' AND (status IN ('awaiting_payment', 'draft', 'refunded') OR submitted_at IS NOT NULL)) OR (path = 'advanced' AND (status IN ('rejected_conditions', 'draft', 'expired', 'refunded') OR submitted_at IS NOT NULL))",
    ];

    public function up(): void
    {
        $this->replace(array_keys(self::PREVIOUS), self::CURRENT);
    }

    public function down(): void
    {
        $blocking = DB::table('assessments')->where('path', '<>', 'accredited')->whereNotNull('payment_id')->exists()
            || DB::table('assessments')->where('path', 'accredited')->whereNull('payment_id')->exists()
            || DB::table('assessments')->where('path', '<>', 'accredited')->whereIn('status', ['awaiting_payment', 'refunded'])->exists();

        if ($blocking) {
            throw new RuntimeException('Paid self / award requests or free accredited requests exist; this migration cannot be rolled back without losing request records.');
        }

        $this->replace(array_keys(self::CURRENT), self::PREVIOUS);
    }

    /**
     * @param  list<string>  $dropped
     * @param  array<string, string>  $added
     */
    private function replace(array $dropped, array $added): void
    {
        foreach ($dropped as $name) {
            DB::statement(sprintf('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_%s_check', $name));
        }

        foreach ($added as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessments ADD CONSTRAINT assessments_%s_check CHECK (%s)', $name, $expression));
        }
    }
};
