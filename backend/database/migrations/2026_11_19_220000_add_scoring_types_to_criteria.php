<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each scored unit (a directly evaluated sub-criterion or an indicator) gets its own scoring type, and the main and
 * sub-criteria get the default type for their new children (operator decisions D-1 to D-3, 2026-11-19).
 *
 * Every existing node takes the scale of its version: `of_10` → `score`, `relative` → `relative`. A draft has no
 * stamped scale yet, so it takes the platform scale (setting, then deployment default). Stored scores keep their
 * meaning, so no score, result or certificate changes. Published and archived versions are frozen by the structure
 * guards; the backfill alone runs with those row triggers switched off, inside this migration's transaction.
 */
return new class extends Migration
{
    private const array GUARDED_TABLES = ['main_criteria', 'sub_criteria', 'guiding_points'];

    private const string TYPES = "('relative', 'score', 'achievement')";

    private const array CHECKED_COLUMNS = [
        'main_criteria' => ['default_scoring_type'],
        'sub_criteria' => ['scoring_type', 'default_point_scoring_type'],
        'guiding_points' => ['scoring_type'],
    ];

    public function up(): void
    {
        Schema::table('main_criteria', function (Blueprint $table): void {
            $table->string('default_scoring_type', 20)->nullable();
        });
        Schema::table('sub_criteria', function (Blueprint $table): void {
            $table->string('scoring_type', 20)->nullable();
            $table->string('default_point_scoring_type', 20)->nullable();
        });
        Schema::table('guiding_points', function (Blueprint $table): void {
            $table->string('scoring_type', 20)->nullable();
        });

        $this->withoutStructureGuards(fn () => $this->backfill($this->platformType()));

        foreach (self::CHECKED_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ".self::TYPES.')');
            }
        }
    }

    public function down(): void
    {
        Schema::table('guiding_points', function (Blueprint $table): void {
            $table->dropColumn('scoring_type');
        });
        Schema::table('sub_criteria', function (Blueprint $table): void {
            $table->dropColumn(['scoring_type', 'default_point_scoring_type']);
        });
        Schema::table('main_criteria', function (Blueprint $table): void {
            $table->dropColumn('default_scoring_type');
        });
    }

    private function backfill(string $platformType): void
    {
        DB::update(<<<'SQL'
            UPDATE main_criteria m SET default_scoring_type = CASE v.scale_kind
                WHEN 'relative' THEN 'relative'
                WHEN 'of_10' THEN 'score'
                ELSE ? END
            FROM criteria_versions v WHERE v.id = m.criteria_version_id
            SQL, [$platformType]);
        DB::update(<<<'SQL'
            UPDATE sub_criteria s SET scoring_type = m.default_scoring_type, default_point_scoring_type = m.default_scoring_type
            FROM main_criteria m WHERE m.id = s.main_criterion_id
            SQL);
        DB::update(<<<'SQL'
            UPDATE guiding_points g SET scoring_type = s.default_point_scoring_type
            FROM sub_criteria s WHERE s.id = g.sub_criterion_id
            SQL);
    }

    private function platformType(): string
    {
        $stored = DB::table('settings')->where('key', 'assessment.scale_kind')->value('value');
        $chosen = is_string($stored) ? json_decode($stored, true) : null;
        $scale = is_string($chosen) ? $chosen : config('moashir.assessment.scale_kind');

        return $scale === 'relative' ? 'relative' : 'score';
    }

    private function withoutStructureGuards(callable $change): void
    {
        foreach (self::GUARDED_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DISABLE TRIGGER {$table}_structure_guard");
        }

        try {
            $change();
        } finally {
            foreach (self::GUARDED_TABLES as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE TRIGGER {$table}_structure_guard");
            }
        }
    }
};
