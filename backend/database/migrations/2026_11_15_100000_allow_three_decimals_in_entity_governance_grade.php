<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->decimal('governance_grade', 6, 3)->nullable()->change();
        });
    }

    /**
     * Going back rounds every stored grade to two decimals.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->decimal('governance_grade', 5, 2)->nullable()->change();
        });
    }
};
