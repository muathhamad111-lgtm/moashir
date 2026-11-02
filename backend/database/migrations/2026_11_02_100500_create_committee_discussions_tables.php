<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'mode' => "mode IN ('session', 'agreed_outcome')",
        'status' => "status IN ('open', 'closed', 'cancelled')",
        'session' => "mode <> 'session' OR deadline_at IS NOT NULL",
        'outcome' => "mode <> 'agreed_outcome' OR agreed_outcome IS NOT NULL",
        'closed' => "(status = 'open') = (closed_at IS NULL)",
    ];

    public function up(): void
    {
        Schema::create('committee_discussions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20);
            $table->string('status', 10);
            $table->timestampTz('deadline_at')->nullable();
            $table->text('agreed_outcome')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('opened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['assessment_id', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE committee_discussions ADD CONSTRAINT committee_discussions_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX committee_discussions_one_open ON committee_discussions (assessment_id) WHERE status = 'open'");

        Schema::create('committee_discussion_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('committee_discussion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_committee_assessor_id')->index()->constrained()->restrictOnDelete();
            $table->string('kind', 15);
            $table->text('body')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('created_at');
        });

        DB::statement("ALTER TABLE committee_discussion_entries ADD CONSTRAINT committee_discussion_entries_kind_check CHECK (kind IN ('opinion', 'approve', 'request_edit') AND (kind = 'approve' OR body IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX committee_discussion_entries_current ON committee_discussion_entries (committee_discussion_id, cycle_committee_assessor_id) WHERE superseded_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER committee_discussion_entries_versioned BEFORE UPDATE OR DELETE ON committee_discussion_entries
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_assessment_scores();
            CREATE TRIGGER committee_discussion_entries_versioned_truncate BEFORE TRUNCATE ON committee_discussion_entries
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS committee_discussion_entries_versioned_truncate ON committee_discussion_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS committee_discussion_entries_versioned ON committee_discussion_entries');
        Schema::dropIfExists('committee_discussion_entries');
        Schema::dropIfExists('committee_discussions');
    }
};
