<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string CHECK = 'assessments_criteria_update_redispatches_check';

    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('criteria_update_redispatches')->default(0);
            $table->index('criteria_update_pending_since');
        });

        DB::statement(sprintf('ALTER TABLE assessments ADD CONSTRAINT %s CHECK (criteria_update_redispatches >= 0)', self::CHECK));
    }

    public function down(): void
    {
        DB::statement(sprintf('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS %s', self::CHECK));

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropIndex(['criteria_update_pending_since']);
            $table->dropColumn('criteria_update_redispatches');
        });
    }
};
