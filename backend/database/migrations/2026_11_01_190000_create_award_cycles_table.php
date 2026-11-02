<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('draft', 'submission_open', 'submission_closed', 'committee_assessment', 'awaiting_secretary', 'approved', 'announced', 'closed')",
        'scale_kind' => "scale_kind IN ('of_10', 'relative')",
        'dates' => 'submission_start < submission_end AND submission_end <= committee_start AND committee_start < committee_end AND (announcement_at IS NULL OR announcement_at >= committee_end)',
        'conditions' => 'min_entity_age_years BETWEEN 0 AND 100 AND disqualification_score BETWEEN 0 AND 100',
        'review_sla' => 'initial_review_sla_days BETWEEN 1 AND 120',
        'closed' => "(status = 'closed') = (closed_at IS NOT NULL) AND (status = 'closed') = (closed_from_status IS NOT NULL)",
        'year' => 'year BETWEEN 2000 AND 2200',
    ];

    public function up(): void
    {
        Schema::create('award_cycles', function (Blueprint $table): void {
            $table->id();
            $table->jsonb('name');
            $table->unsignedSmallInteger('year');
            $table->string('status', 25);
            $table->foreignId('criteria_version_id')->index()->constrained()->restrictOnDelete();
            $table->string('scale_kind', 10);
            $table->timestampTz('submission_start');
            $table->timestampTz('submission_end');
            $table->timestampTz('committee_start');
            $table->timestampTz('committee_end');
            $table->timestampTz('announcement_at')->nullable();
            $table->unsignedSmallInteger('min_entity_age_years');
            $table->decimal('disqualification_score', 5, 2);
            $table->unsignedSmallInteger('initial_review_sla_days');
            $table->boolean('participation_certificate_enabled')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('submissions_closed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('closed_from_status', 25)->nullable();
            $table->timestampTz('announced_at')->nullable();
            $table->timestampsTz();

            $table->index('status');
            $table->index(['submission_start', 'submission_end']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE award_cycles ADD CONSTRAINT award_cycles_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX award_cycles_one_running ON award_cycles ((true)) WHERE status NOT IN ('draft', 'closed')");

        Schema::create('award_cycle_regions', function (Blueprint $table): void {
            $table->foreignId('award_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->index()->constrained()->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['award_cycle_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('award_cycle_regions');
        Schema::dropIfExists('award_cycles');
    }
};
