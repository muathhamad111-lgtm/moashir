<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string STATUS_CHECK = 'assessments_status_check';

    private const string SELF_STATES = "path = 'self' AND status IN ('draft', 'submitted', 'result_available')";

    private const string PREVIOUS_ACCREDITED_STATES = "'awaiting_payment', 'draft', 'submitted', 'assigned', 'under_review', 'needs_completion', 'completed', 'refunded'";

    public function up(): void
    {
        $this->replaceStatusCheck(self::PREVIOUS_ACCREDITED_STATES.", 'declined_incomplete'");
    }

    public function down(): void
    {
        $this->replaceStatusCheck(self::PREVIOUS_ACCREDITED_STATES);
    }

    private function replaceStatusCheck(string $accreditedStates): void
    {
        DB::statement(sprintf('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS %s', self::STATUS_CHECK));
        DB::statement(sprintf(
            "ALTER TABLE assessments ADD CONSTRAINT %s CHECK ((%s) OR (path = 'accredited' AND status IN (%s)))",
            self::STATUS_CHECK,
            self::SELF_STATES,
            $accreditedStates,
        ));
    }
};
