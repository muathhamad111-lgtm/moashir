<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('active', 'disabled', 'excluded', 'substituted', 'removed')",
        'disabled' => "status NOT IN ('disabled', 'excluded', 'substituted') OR (disabled_at IS NOT NULL AND disabled_reason IS NOT NULL)",
        'removed' => "(status = 'removed') = (removed_at IS NOT NULL)",
        'not_self' => 'replaces_id IS NULL OR replaces_id <> id',
    ];

    public function up(): void
    {
        Schema::create('cycle_committee_assessors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('assessor_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('status', 15);
            $table->foreignId('replaces_id')->nullable()->unique()->constrained('cycle_committee_assessors')->restrictOnDelete();
            $table->foreignId('appointed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('disabled_at')->nullable();
            $table->text('disabled_reason')->nullable();
            $table->foreignId('disabled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('removed_at')->nullable();
            $table->timestampTz('overdue_notified_at')->nullable();
            $table->timestampsTz();

            $table->index(['award_cycle_id', 'status']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE cycle_committee_assessors ADD CONSTRAINT cycle_committee_assessors_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX cycle_committee_assessors_one_seat ON cycle_committee_assessors (award_cycle_id, assessor_user_id) WHERE status <> 'removed'");
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_committee_assessors');
    }
};
