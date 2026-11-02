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
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->index()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20)->default('open');
            $table->text('details');
            $table->foreignId('created_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_kind_check CHECK (kind IN ('access', 'correction', 'deletion'))");
        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_status_check CHECK (status IN ('open', 'fulfilled', 'rejected'))");
        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_decision_check CHECK (status = 'open' OR (decided_at IS NOT NULL AND decided_by_user_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
