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
        Schema::create('breached_passwords', function (Blueprint $table): void {
            $table->char('digest', 64)->primary();
        });

        DB::statement("ALTER TABLE breached_passwords ADD CONSTRAINT breached_passwords_digest_check CHECK (digest ~ '^[0-9a-f]{64}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('breached_passwords');
    }
};
