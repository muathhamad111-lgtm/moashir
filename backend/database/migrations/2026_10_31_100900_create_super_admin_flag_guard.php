<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_super_admin_flag() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'INSERT' AND NOT NEW.is_super_admin THEN
                    RETURN NEW;
                END IF;
                IF TG_OP = 'UPDATE' AND NEW.is_super_admin IS NOT DISTINCT FROM OLD.is_super_admin THEN
                    RETURN NEW;
                END IF;
                IF coalesce(current_setting('moashir.console_context', true), '') <> 'on' THEN
                    RAISE EXCEPTION 'super_admin_flag: changes are allowed only from the console command'
                        USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        DB::unprepared('CREATE TRIGGER users_super_admin_flag_guard BEFORE INSERT OR UPDATE OF is_super_admin ON users FOR EACH ROW EXECUTE FUNCTION moashir_guard_super_admin_flag()');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_super_admin_flag_guard ON users');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_super_admin_flag()');
    }
};
