<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('uploaded', 'removed')",
        'removed' => "(status = 'removed') = (removed_at IS NOT NULL)",
    ];

    public function up(): void
    {
        Schema::create('evidence_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('sub_code', 20);
            $table->string('attachment_key', 40)->nullable();
            $table->foreignId('stored_file_id')->unique()->constrained('stored_files')->restrictOnDelete();
            $table->string('status', 10);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('removed_at')->nullable();
            $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['assessment_id', 'sub_code', 'status']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE evidence_files ADD CONSTRAINT evidence_files_%s_check CHECK (%s)', $name, $expression));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
    }
};
