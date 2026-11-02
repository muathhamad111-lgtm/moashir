<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'kind' => "kind IN ('strength', 'improvement', 'note')",
        'version' => 'version >= 1',
        'body' => "btrim(body) <> ''",
    ];

    public function up(): void
    {
        Schema::create('assessment_feedback_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('sub_code', 20);
            $table->string('kind', 15);
            $table->text('body');
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('change_reason')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['assessment_id', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_feedback_items ADD CONSTRAINT assessment_feedback_items_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement('CREATE UNIQUE INDEX assessment_feedback_items_current ON assessment_feedback_items (assessment_id, sub_code, kind) WHERE superseded_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_supersede_once() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND moashir_entity_erasure_active() THEN
                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE'
                    AND OLD.superseded_at IS NULL
                    AND NEW.superseded_at IS NOT NULL
                    AND (to_jsonb(NEW) - 'superseded_at') = (to_jsonb(OLD) - 'superseded_at') THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER assessment_feedback_items_versioned BEFORE UPDATE OR DELETE ON assessment_feedback_items
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_supersede_once();
            CREATE TRIGGER assessment_feedback_items_versioned_truncate BEFORE TRUNCATE ON assessment_feedback_items
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_feedback_items_versioned_truncate ON assessment_feedback_items');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_feedback_items_versioned ON assessment_feedback_items');
        Schema::dropIfExists('assessment_feedback_items');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_supersede_once()');
    }
};
