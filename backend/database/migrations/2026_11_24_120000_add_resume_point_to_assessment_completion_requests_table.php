<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string RESUMES_TO_CHECK = 'assessment_completion_requests_resumes_to_check';

    private const string DAYS_CHECK = 'assessment_completion_requests_review_days_remaining_check';

    public function up(): void
    {
        Schema::table('assessment_completion_requests', function (Blueprint $table): void {
            $table->string('resumes_to', 30)->nullable();
            $table->unsignedSmallInteger('review_days_remaining')->nullable();
        });

        DB::statement(sprintf("ALTER TABLE assessment_completion_requests ADD CONSTRAINT %s CHECK (resumes_to IS NULL OR resumes_to IN ('assigned', 'under_initial_assessment'))", self::RESUMES_TO_CHECK));
        DB::statement(sprintf('ALTER TABLE assessment_completion_requests ADD CONSTRAINT %s CHECK (review_days_remaining IS NULL OR (resumes_to IS NOT NULL AND review_days_remaining >= 0))', self::DAYS_CHECK));
    }

    public function down(): void
    {
        DB::statement(sprintf('ALTER TABLE assessment_completion_requests DROP CONSTRAINT IF EXISTS %s', self::DAYS_CHECK));
        DB::statement(sprintf('ALTER TABLE assessment_completion_requests DROP CONSTRAINT IF EXISTS %s', self::RESUMES_TO_CHECK));

        Schema::table('assessment_completion_requests', function (Blueprint $table): void {
            $table->dropColumn(['resumes_to', 'review_days_remaining']);
        });
    }
};
