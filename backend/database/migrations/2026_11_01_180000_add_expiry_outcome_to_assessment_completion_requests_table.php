<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string STATUS_CHECK = 'assessment_completion_requests_status_check';

    private const string OUTCOME_CHECK = 'assessment_completion_requests_expiry_outcome_check';

    private const string OPEN_DEADLINE_INDEX = 'assessment_completion_requests_open_deadline';

    public function up(): void
    {
        Schema::table('assessment_completion_requests', function (Blueprint $table): void {
            $table->string('expiry_outcome', 25)->nullable();
            $table->string('status', 20)->change();
        });

        DB::table('assessment_completion_requests')->whereNull('expiry_outcome')->update(['expiry_outcome' => 'continue_as_submitted']);
        DB::statement('ALTER TABLE assessment_completion_requests ALTER COLUMN expiry_outcome SET NOT NULL');
        DB::statement(sprintf("ALTER TABLE assessment_completion_requests ADD CONSTRAINT %s CHECK (expiry_outcome IN ('continue_as_submitted', 'decline'))", self::OUTCOME_CHECK));
        $this->replaceStatusCheck("status IN ('open', 'resubmitted', 'cancelled', 'expired_continued', 'expired_declined')");
        DB::statement(sprintf("CREATE INDEX %s ON assessment_completion_requests (deadline_at) WHERE status = 'open'", self::OPEN_DEADLINE_INDEX));
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::OPEN_DEADLINE_INDEX));
        $this->replaceStatusCheck("status IN ('open', 'resubmitted', 'cancelled')");
        DB::statement(sprintf('ALTER TABLE assessment_completion_requests DROP CONSTRAINT IF EXISTS %s', self::OUTCOME_CHECK));

        Schema::table('assessment_completion_requests', function (Blueprint $table): void {
            $table->dropColumn('expiry_outcome');
            $table->string('status', 15)->change();
        });
    }

    private function replaceStatusCheck(string $expression): void
    {
        DB::statement(sprintf('ALTER TABLE assessment_completion_requests DROP CONSTRAINT IF EXISTS %s', self::STATUS_CHECK));
        DB::statement(sprintf('ALTER TABLE assessment_completion_requests ADD CONSTRAINT %s CHECK (%s)', self::STATUS_CHECK, $expression));
    }
};
