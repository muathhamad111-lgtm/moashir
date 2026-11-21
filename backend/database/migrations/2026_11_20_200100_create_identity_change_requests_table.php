<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string ONE_PENDING = 'identity_change_requests_one_pending_per_user';

    public function up(): void
    {
        Schema::create('identity_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('new_name', 150)->nullable();
            $table->text('new_national_id')->nullable();
            $table->text('reason');
            $table->string('status', 20)->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });

        DB::statement(sprintf("CREATE UNIQUE INDEX %s ON identity_change_requests (user_id) WHERE status = 'pending'", self::ONE_PENDING));
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_change_requests');
    }
};
