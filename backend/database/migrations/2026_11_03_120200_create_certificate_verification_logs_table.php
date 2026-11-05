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
        Schema::create('certificate_verification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certificate_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('outcome', 12);
            $table->char('ip_hash', 64);
            $table->timestampTz('created_at')->index();
        });

        DB::statement("ALTER TABLE certificate_verification_logs ADD CONSTRAINT certificate_verification_logs_outcome_check CHECK (outcome IN ('valid', 'revoked', 'superseded', 'not_found'))");
        DB::statement("ALTER TABLE certificate_verification_logs ADD CONSTRAINT certificate_verification_logs_found_check CHECK ((outcome = 'not_found') = (certificate_id IS NULL))");

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER certificate_verification_logs_no_update BEFORE UPDATE ON certificate_verification_logs
                FOR EACH ROW EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS certificate_verification_logs_no_update ON certificate_verification_logs');
        Schema::dropIfExists('certificate_verification_logs');
    }
};
