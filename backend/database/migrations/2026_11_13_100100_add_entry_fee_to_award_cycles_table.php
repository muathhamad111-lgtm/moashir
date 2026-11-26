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
        Schema::table('award_cycles', function (Blueprint $table): void {
            $table->decimal('entry_fee', 10, 2)->default(0);
        });

        DB::statement('ALTER TABLE award_cycles ADD CONSTRAINT award_cycles_entry_fee_check CHECK (entry_fee >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE award_cycles DROP CONSTRAINT IF EXISTS award_cycles_entry_fee_check');

        Schema::table('award_cycles', function (Blueprint $table): void {
            $table->dropColumn('entry_fee');
        });
    }
};
