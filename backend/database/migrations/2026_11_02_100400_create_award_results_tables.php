<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_rankings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('committee_average_id')->constrained('committee_averages')->cascadeOnDelete();
            $table->decimal('average_percent', 9, 4);
            $table->unsignedSmallInteger('regional_rank')->nullable();
            $table->unsignedSmallInteger('national_rank')->nullable();
            $table->foreignId('ranked_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['award_cycle_id', 'region_id', 'average_percent']);
        });

        DB::statement('ALTER TABLE award_rankings ADD CONSTRAINT award_rankings_ranks_check CHECK ((regional_rank IS NULL OR regional_rank BETWEEN 1 AND 3) AND (national_rank IS NULL OR national_rank BETWEEN 1 AND 3) AND (national_rank IS NULL OR regional_rank IS NOT NULL))');
        DB::statement('CREATE UNIQUE INDEX award_rankings_regional_rank ON award_rankings (award_cycle_id, region_id, regional_rank) WHERE regional_rank IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX award_rankings_national_rank ON award_rankings (award_cycle_id, national_rank) WHERE national_rank IS NOT NULL');

        Schema::create('award_region_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('winners_count');
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at');
            $table->timestampTz('unapproved_at')->nullable();
            $table->foreignId('unapproved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('unapprove_reason')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE award_region_approvals ADD CONSTRAINT award_region_approvals_unapproved_check CHECK ((unapproved_at IS NULL) = (unapprove_reason IS NULL) AND winners_count >= 1)');
        DB::statement('CREATE UNIQUE INDEX award_region_approvals_current ON award_region_approvals (award_cycle_id, region_id) WHERE unapproved_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('award_region_approvals');
        Schema::dropIfExists('award_rankings');
    }
};
