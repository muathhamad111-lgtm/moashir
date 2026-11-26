<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * From criteria batch S4 every scored unit carries its own scoring type, so new requests, their result snapshots and
 * award cycles are no longer stamped with one scale. Rows stamped before keep their stamp: a stamped request keeps
 * scoring every unit on that scale, so its stored raw values keep their meaning.
 *
 * Rolling back stamps the unstamped rows with the scale of their criteria version (published versions always have one).
 */
return new class extends Migration
{
    private const array TABLES = ['assessments', 'assessment_result_snapshots', 'award_cycles'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN scale_kind DROP NOT NULL");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assessment_result_snapshots DISABLE TRIGGER USER');

        try {
            foreach (self::TABLES as $table) {
                DB::update(<<<SQL
                    UPDATE {$table} t SET scale_kind = coalesce(v.scale_kind, 'of_10')
                    FROM criteria_versions v WHERE v.id = t.criteria_version_id AND t.scale_kind IS NULL
                    SQL);
                DB::statement("ALTER TABLE {$table} ALTER COLUMN scale_kind SET NOT NULL");
            }
        } finally {
            DB::statement('ALTER TABLE assessment_result_snapshots ENABLE TRIGGER USER');
        }
    }
};
