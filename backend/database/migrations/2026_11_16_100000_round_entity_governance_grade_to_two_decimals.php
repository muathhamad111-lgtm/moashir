<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Governance grades are shown and entered with two decimals (operator decision, 2026-11-16):
     * existing three-decimal values are rounded half up before the column narrows.
     */
    public function up(): void
    {
        DB::table('entities')->whereNotNull('governance_grade')->update(['governance_grade' => DB::raw('ROUND(governance_grade, 2)')]);

        Schema::table('entities', function (Blueprint $table): void {
            $table->decimal('governance_grade', 5, 2)->nullable()->change();
        });
    }

    /**
     * Going back widens the column again; the rounded values stay as they are.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->decimal('governance_grade', 6, 3)->nullable()->change();
        });
    }
};
