<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('preparing', 'ready', 'failed', 'expired')",
        'ready' => "status <> 'ready' OR (stored_file_id IS NOT NULL AND expires_at IS NOT NULL)",
    ];

    public function up(): void
    {
        Schema::create('evidence_bundles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 10);
            $table->unsignedInteger('file_count');
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('stored_file_id')->nullable()->unique()->constrained('stored_files')->restrictOnDelete();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['assessment_id', 'requested_by_user_id']);
            $table->index(['status', 'expires_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE evidence_bundles ADD CONSTRAINT evidence_bundles_%s_check CHECK (%s)', $name, $expression));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_bundles');
    }
};
