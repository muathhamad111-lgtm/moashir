<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'entities_declined_governance_grade_check' => 'has_governance_grade IS DISTINCT FROM false OR governance_grade IS NULL',
        'entities_answered_governance_grade_check' => 'has_governance_grade IS DISTINCT FROM true OR governance_grade IS NOT NULL',
    ];

    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->boolean('has_governance_grade')->nullable();
        });

        DB::table('entities')->whereNotNull('governance_grade')->update(['has_governance_grade' => true]);

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE entities ADD CONSTRAINT %s CHECK (%s)', $name, $expression));
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::CHECKS) as $name) {
            DB::statement(sprintf('ALTER TABLE entities DROP CONSTRAINT IF EXISTS %s', $name));
        }

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropColumn('has_governance_grade');
        });
    }
};
