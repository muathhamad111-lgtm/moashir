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
        Schema::create('yearly_number_sequences', function (Blueprint $table): void {
            $table->string('series', 60);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_value');

            $table->primary(['series', 'year']);
        });

        DB::statement('ALTER TABLE yearly_number_sequences ADD CONSTRAINT yearly_number_sequences_last_value_check CHECK (last_value >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('yearly_number_sequences');
    }
};
