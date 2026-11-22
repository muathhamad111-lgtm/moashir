<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A directly evaluated sub-criterion keeps its indicators as descriptive items with their own attachments
 * (operator, 2026-11-22). Such indicators are not scored, so they carry no weight: the weight becomes optional,
 * and a stored weight must still be positive.
 *
 * Existing rows are untouched: every indicator today belongs to a sub-criterion scored on indicators and keeps its
 * weight. The rollback restores NOT NULL only while no indicator is unweighted; once one exists (a published version
 * is frozen and keeps it) the rollback stops with a clear message instead of inventing weights.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE guiding_points DROP CONSTRAINT guiding_points_weight_check');
        DB::statement('ALTER TABLE guiding_points ALTER COLUMN weight DROP NOT NULL');
        DB::statement('ALTER TABLE guiding_points ADD CONSTRAINT guiding_points_weight_check CHECK (weight IS NULL OR weight > 0)');
    }

    public function down(): void
    {
        if (DB::table('guiding_points')->whereNull('weight')->exists()) {
            throw new RuntimeException('Indicators without a weight exist (directly evaluated sub-criteria); the weight cannot become required again.');
        }

        DB::statement('ALTER TABLE guiding_points DROP CONSTRAINT guiding_points_weight_check');
        DB::statement('ALTER TABLE guiding_points ALTER COLUMN weight SET NOT NULL');
        DB::statement('ALTER TABLE guiding_points ADD CONSTRAINT guiding_points_weight_check CHECK (weight > 0)');
    }
};
