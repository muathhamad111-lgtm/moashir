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
        Schema::create('demo_records', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->unsignedBigInteger('record_id');
            $table->timestampsTz();

            $table->unique(['kind', 'record_id']);
        });

        DB::statement("ALTER TABLE demo_records ADD CONSTRAINT demo_records_kind_check CHECK (kind IN ('entity', 'user'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_records');
    }
};
