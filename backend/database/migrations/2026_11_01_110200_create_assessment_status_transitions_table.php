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
        Schema::create('assessment_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('verb', 30);
            $table->foreignId('actor_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');

            $table->index(['assessment_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_erasable_history() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND moashir_entity_erasure_active() THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER assessment_status_transitions_append_only BEFORE UPDATE OR DELETE ON assessment_status_transitions
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_erasable_history();
            CREATE TRIGGER assessment_status_transitions_append_only_truncate BEFORE TRUNCATE ON assessment_status_transitions
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_status_transitions_append_only_truncate ON assessment_status_transitions');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_status_transitions_append_only ON assessment_status_transitions');
        Schema::dropIfExists('assessment_status_transitions');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_erasable_history()');
    }
};
