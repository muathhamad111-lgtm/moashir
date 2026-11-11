<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->boolean('has_strategic_plan')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('entities')->whereNull('has_strategic_plan')->update(['has_strategic_plan' => false]);

        Schema::table('entities', function (Blueprint $table): void {
            $table->boolean('has_strategic_plan')->default(false)->change();
        });
    }
};
