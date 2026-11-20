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
        Schema::create('file_access_traces', function (Blueprint $table): void {
            $table->id();
            $table->string('trace_code', 24)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('stored_file_id');
            $table->uuid('stored_file_public_id');
            $table->string('purpose', 40);
            $table->string('intent', 10);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['stored_file_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER file_access_traces_append_only BEFORE UPDATE OR DELETE ON file_access_traces
                FOR EACH ROW EXECUTE FUNCTION moashir_reject_append_only_change();
            CREATE TRIGGER file_access_traces_append_only_truncate BEFORE TRUNCATE ON file_access_traces
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS file_access_traces_append_only_truncate ON file_access_traces');
        DB::unprepared('DROP TRIGGER IF EXISTS file_access_traces_append_only ON file_access_traces');
        Schema::dropIfExists('file_access_traces');
    }
};
