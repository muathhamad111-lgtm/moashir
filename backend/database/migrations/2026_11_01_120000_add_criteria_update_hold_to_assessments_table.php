<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string CHECK = 'assessments_criteria_update_hold_check';

    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->timestampTz('criteria_update_pending_since')->nullable();
        });

        DB::statement(sprintf(
            'ALTER TABLE assessments ADD CONSTRAINT %s CHECK (criteria_update_pending_since IS NULL OR submitted_at IS NULL)',
            self::CHECK,
        ));
    }

    public function down(): void
    {
        DB::statement(sprintf('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS %s', self::CHECK));

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn('criteria_update_pending_since');
        });
    }
};
