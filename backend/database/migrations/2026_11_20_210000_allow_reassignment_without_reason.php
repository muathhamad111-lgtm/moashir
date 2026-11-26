<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin review 2026-11-20 (R3): whether a reassignment needs a reason is now a general setting
 * (`assessment.reassign_reason_required`), so the table no longer forces one on every reassigned row.
 */
return new class extends Migration
{
    private const string CHECK = 'assessment_assignments_reassign_reason_check';

    public function up(): void
    {
        DB::statement('ALTER TABLE assessment_assignments DROP CONSTRAINT IF EXISTS '.self::CHECK);
    }

    /**
     * The rows are append-only, so a reassignment saved without a reason cannot be backfilled: the check comes back
     * NOT VALID, which enforces it on new rows and leaves the history as it is.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE assessment_assignments ADD CONSTRAINT '.self::CHECK." CHECK (status <> 'reassigned' OR end_reason IS NOT NULL) NOT VALID");
    }
};
