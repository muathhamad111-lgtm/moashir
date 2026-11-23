<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'level' => "level IN ('main', 'sub', 'point')",
        'unit' => "(level = 'main' AND sub_criterion_id IS NULL AND guiding_point_id IS NULL)"
            ." OR (level = 'sub' AND sub_criterion_id IS NOT NULL AND guiding_point_id IS NULL)"
            ." OR (level = 'point' AND sub_criterion_id IS NOT NULL AND guiding_point_id IS NOT NULL)",
        'body' => "btrim(body) <> ''",
        'version' => 'version >= 1',
        'author_role' => "author_role IN ('super_admin', 'assessor', 'follower', 'admin')",
    ];

    public function up(): void
    {
        Schema::create('assessment_review_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('level', 10);
            $table->foreignId('main_criterion_id')->constrained('main_criteria')->restrictOnDelete();
            $table->foreignId('sub_criterion_id')->nullable()->constrained('sub_criteria')->restrictOnDelete();
            $table->foreignId('guiding_point_id')->nullable()->constrained('guiding_points')->restrictOnDelete();
            $table->foreignId('author_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('author_role', 20);
            $table->text('body');
            $table->unsignedInteger('version');
            $table->text('change_reason')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['assessment_id', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_review_notes ADD CONSTRAINT assessment_review_notes_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement('CREATE UNIQUE INDEX assessment_review_notes_current ON assessment_review_notes (assessment_id, level, main_criterion_id, coalesce(sub_criterion_id, 0), coalesce(guiding_point_id, 0), author_user_id) WHERE superseded_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER assessment_review_notes_versioned BEFORE UPDATE OR DELETE ON assessment_review_notes
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_supersede_once();
            CREATE TRIGGER assessment_review_notes_versioned_truncate BEFORE TRUNCATE ON assessment_review_notes
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_review_notes_versioned_truncate ON assessment_review_notes');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_review_notes_versioned ON assessment_review_notes');
        Schema::dropIfExists('assessment_review_notes');
    }
};
