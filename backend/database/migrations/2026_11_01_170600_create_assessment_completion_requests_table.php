<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('open', 'resubmitted', 'cancelled')",
        'resolved' => "(status = 'open') = (resolved_at IS NULL)",
        'items' => "jsonb_typeof(items) = 'array' AND jsonb_array_length(items) > 0",
    ];

    public function up(): void
    {
        Schema::create('assessment_completion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->text('notes');
            $table->jsonb('items');
            $table->timestampTz('deadline_at');
            $table->string('status', 15);
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['assessment_id', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_completion_requests ADD CONSTRAINT assessment_completion_requests_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX assessment_completion_requests_one_open ON assessment_completion_requests (assessment_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_completion_requests');
    }
};
