<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string CONSTRAINT = 'filtering_decisions_override_check';

    /**
     * New decisions compare the initial result with the disqualification score as both are displayed
     * (two decimals, half up — operator decision, 2026-11-18). Decisions recorded before compared four
     * decimals; the check accepts both so that those rows stay valid:
     * an override is only possible below the score, and is required when the displayed result is below it.
     */
    public function up(): void
    {
        DB::statement(sprintf('ALTER TABLE filtering_decisions DROP CONSTRAINT %s', self::CONSTRAINT));
        DB::statement(sprintf(
            "ALTER TABLE filtering_decisions ADD CONSTRAINT %s CHECK ((NOT is_override OR (outcome = 'qualified' AND initial_percent < threshold_percent)) AND (is_override OR NOT (outcome = 'qualified' AND ROUND(initial_percent, 2) < threshold_percent)))",
            self::CONSTRAINT,
        ));
    }

    /**
     * Restores the four-decimal rule. It fails if a decision taken under the displayed rule sits in the
     * half-hundredth just below its score; such a row must be reviewed before rolling back.
     */
    public function down(): void
    {
        DB::statement(sprintf('ALTER TABLE filtering_decisions DROP CONSTRAINT %s', self::CONSTRAINT));
        DB::statement(sprintf(
            "ALTER TABLE filtering_decisions ADD CONSTRAINT %s CHECK (is_override = (outcome = 'qualified' AND initial_percent < threshold_percent))",
            self::CONSTRAINT,
        ));
    }
};
