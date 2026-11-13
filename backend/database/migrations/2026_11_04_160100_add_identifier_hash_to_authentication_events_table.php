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
        Schema::table('authentication_events', function (Blueprint $table): void {
            $table->string('identifier_type', 20)->nullable();
            $table->char('identifier_hash', 64)->nullable();

            $table->index(['identifier_hash', 'created_at']);
        });

        DB::statement("ALTER TABLE authentication_events ADD CONSTRAINT authentication_events_identifier_type_check CHECK (identifier_type IS NULL OR identifier_type IN ('email', 'mobile', 'unified_number'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE authentication_events DROP CONSTRAINT IF EXISTS authentication_events_identifier_type_check');

        Schema::table('authentication_events', function (Blueprint $table): void {
            $table->dropIndex(['identifier_hash', 'created_at']);
            $table->dropColumn(['identifier_type', 'identifier_hash']);
        });
    }
};
