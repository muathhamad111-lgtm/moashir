<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_search_normalize(value text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
            AS $$
                SELECT btrim(regexp_replace(
                    translate(
                        lower(regexp_replace(value, '[ً-ٰٟـ]', '', 'g')),
                        'أإآٱىئؤة٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹',
                        'ااااييوه01234567890123456789'
                    ),
                    '\s+', ' ', 'g'
                ))
            $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_search_normalize(text)');
    }
};
