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
        Schema::table('stored_files', function (Blueprint $table): void {
            $table->timestampTz('quarantined_at')->nullable();
        });

        DB::statement("ALTER TABLE stored_files ADD CONSTRAINT stored_files_quarantine_check CHECK (quarantined_at IS NULL OR malware_scan_status = 'infected')");
        DB::statement('CREATE INDEX stored_files_orphans ON stored_files (created_at) WHERE attachable_type IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stored_files_orphans');
        DB::statement('ALTER TABLE stored_files DROP CONSTRAINT IF EXISTS stored_files_quarantine_check');

        Schema::table('stored_files', function (Blueprint $table): void {
            $table->dropColumn('quarantined_at');
        });
    }
};
