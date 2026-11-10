<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string CHECK = 'certificate_verification_logs_outcome_check';

    private const string PREVIOUS_OUTCOME = "outcome IN ('valid', 'under_review', 'expired', 'revoked', 'superseded', 'not_found')";

    private const string CURRENT_OUTCOME = "outcome IN ('valid', 'under_review', 'expired', 'revoked', 'superseded', 'not_found', 'unverifiable')";

    public function up(): void
    {
        $this->replaceOutcomeCheck(self::CURRENT_OUTCOME);
    }

    public function down(): void
    {
        DB::table('certificate_verification_logs')->where('outcome', 'unverifiable')->update(['outcome' => 'not_found']);
        $this->replaceOutcomeCheck(self::PREVIOUS_OUTCOME);
    }

    private function replaceOutcomeCheck(string $expression): void
    {
        DB::statement(sprintf('ALTER TABLE certificate_verification_logs DROP CONSTRAINT IF EXISTS %s', self::CHECK));
        DB::statement(sprintf('ALTER TABLE certificate_verification_logs ADD CONSTRAINT %s CHECK (%s)', self::CHECK, $expression));
    }
};
