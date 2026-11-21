<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completion rounds (operator decision UX-2, option b): each new score and feedback version records the completion
 * request whose resolution opened its round; null is the first round. Both tables are append-only, so their guards
 * now also accept the one update the foreign key itself makes: unlinking a version from a completion request that a
 * permanent entity deletion erases.
 */
return new class extends Migration
{
    private const array TABLES = ['assessment_scores', 'assessment_feedback_items'];

    private const array GUARDS = ['moashir_guard_assessment_scores', 'moashir_guard_supersede_once'];

    private const string ROUND_UNLINK_RULE = <<<'SQL'

            IF TG_OP = 'UPDATE' AND moashir_round_unlinked_on_erasure(to_jsonb(OLD), to_jsonb(NEW)) THEN
                RETURN NEW;
            END IF;

        SQL;

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('completion_request_id')->nullable()->index()->constrained('assessment_completion_requests')->nullOnDelete();
            });
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_round_unlinked_on_erasure(old_row jsonb, new_row jsonb) RETURNS boolean AS $$
                SELECT moashir_entity_erasure_active()
                    AND old_row ->> 'completion_request_id' IS NOT NULL
                    AND new_row ->> 'completion_request_id' IS NULL
                    AND (new_row - 'completion_request_id') = (old_row - 'completion_request_id');
            $$ LANGUAGE sql STABLE;
            SQL);

        foreach (self::GUARDS as $function) {
            DB::unprepared($this->guard($function, self::ROUND_UNLINK_RULE));
        }
    }

    public function down(): void
    {
        foreach (self::GUARDS as $function) {
            DB::unprepared($this->guard($function, ''));
        }

        DB::unprepared('DROP FUNCTION IF EXISTS moashir_round_unlinked_on_erasure(jsonb, jsonb)');

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('completion_request_id');
            });
        }
    }

    /**
     * The guard body of migrations 2026_11_01_110300 and 2026_11_01_170500, byte for byte when the extra rule is empty.
     *
     * @param  literal-string  $function
     * @param  literal-string  $extraRule
     * @return literal-string
     */
    private function guard(string $function, string $extraRule): string
    {
        return <<<SQL
            CREATE OR REPLACE FUNCTION {$function}() RETURNS trigger AS \$\$
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
            {$extraRule}
                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            \$\$ LANGUAGE plpgsql;
            SQL;
    }
};
