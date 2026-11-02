<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array APPEND_ONLY_TABLES = ['audits', 'authentication_events', 'terms_acceptances'];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_reject_append_only_change() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        foreach (self::APPEND_ONLY_TABLES as $table) {
            DB::unprepared(sprintf(
                'CREATE TRIGGER %1$s_append_only BEFORE UPDATE OR DELETE ON %1$s FOR EACH ROW EXECUTE FUNCTION moashir_reject_append_only_change()',
                $table,
            ));
            DB::unprepared(sprintf(
                'CREATE TRIGGER %1$s_append_only_truncate BEFORE TRUNCATE ON %1$s FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change()',
                $table,
            ));
        }
    }

    public function down(): void
    {
        foreach (self::APPEND_ONLY_TABLES as $table) {
            DB::unprepared(sprintf('DROP TRIGGER IF EXISTS %1$s_append_only ON %1$s', $table));
            DB::unprepared(sprintf('DROP TRIGGER IF EXISTS %1$s_append_only_truncate ON %1$s', $table));
        }

        DB::unprepared('DROP FUNCTION IF EXISTS moashir_reject_append_only_change()');
    }
};
