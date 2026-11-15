<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string STATUS_CHECK = 'entities_status_check';

    private const string REJECTION_CHECK = 'entities_rejection_check';

    private const string PREVIOUS_STATUSES = "status IN ('pending_verification', 'active', 'deactivated')";

    private const string CURRENT_STATUSES = "status IN ('pending_verification', 'pending_review', 'active', 'deactivated', 'rejected')";

    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });

        $this->replaceStatusCheck(self::CURRENT_STATUSES);
        DB::statement(sprintf("ALTER TABLE entities ADD CONSTRAINT %s CHECK (status <> 'rejected' OR rejected_at IS NOT NULL)", self::REJECTION_CHECK));
    }

    /**
     * Restoring the three-status rule fails while any entity awaits review or stands rejected,
     * because those registrations would have no valid status left.
     */
    public function down(): void
    {
        if (DB::table('entities')->whereIn('status', ['pending_review', 'rejected'])->exists()) {
            throw new RuntimeException('Entities await review or stand rejected; decide them before rolling this migration back.');
        }

        DB::statement(sprintf('ALTER TABLE entities DROP CONSTRAINT IF EXISTS %s', self::REJECTION_CHECK));
        $this->replaceStatusCheck(self::PREVIOUS_STATUSES);

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropColumn(['approved_at', 'rejected_at', 'rejection_reason']);
        });
    }

    private function replaceStatusCheck(string $expression): void
    {
        DB::statement(sprintf('ALTER TABLE entities DROP CONSTRAINT IF EXISTS %s', self::STATUS_CHECK));
        DB::statement(sprintf('ALTER TABLE entities ADD CONSTRAINT %s CHECK (%s)', self::STATUS_CHECK, $expression));
    }
};
