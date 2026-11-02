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
        Schema::create('email_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('old_email', 254);
            $table->string('new_email', 254);
            $table->foreignId('otp_code_id')->nullable()->index()->constrained('otp_codes')->nullOnDelete();
            $table->timestampTz('otp_verified_at')->nullable();
            $table->string('policy', 20);
            $table->string('status', 20)->default('awaiting_otp');
            $table->foreignId('decided_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE email_change_requests ADD CONSTRAINT email_change_requests_policy_check CHECK (policy IN ('requires_approval', 'immediate_notify'))");
        DB::statement("ALTER TABLE email_change_requests ADD CONSTRAINT email_change_requests_status_check CHECK (status IN ('awaiting_otp', 'awaiting_approval', 'approved', 'rejected', 'applied', 'cancelled', 'expired'))");
        DB::statement("CREATE UNIQUE INDEX email_change_requests_one_open ON email_change_requests (user_id) WHERE status IN ('awaiting_otp', 'awaiting_approval')");
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_requests');
    }
};
