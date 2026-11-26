<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string ONE_PENDING = 'entity_profile_change_requests_one_pending_per_entity';

    public function up(): void
    {
        Schema::create('entity_profile_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->index();
            $table->unsignedInteger('revision')->default(1);
            $table->text('proposed');
            $table->text('base_values');
            $table->jsonb('changed_fields');
            $table->foreignId('pending_logo_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->boolean('removes_logo')->default(false);
            $table->foreignId('pending_vat_certificate_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->boolean('removes_vat_certificate')->default(false);
            $table->jsonb('decisions')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['entity_id', 'created_at']);
        });

        DB::statement(sprintf("CREATE UNIQUE INDEX %s ON entity_profile_change_requests (entity_id) WHERE status = 'pending'", self::ONE_PENDING));

        Schema::table('entity_profile_changes', function (Blueprint $table): void {
            $table->foreignId('change_request_id')->nullable()->index()->constrained('entity_profile_change_requests')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entity_profile_changes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('change_request_id');
        });

        Schema::dropIfExists('entity_profile_change_requests');
    }
};
