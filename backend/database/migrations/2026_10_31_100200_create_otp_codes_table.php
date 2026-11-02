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
        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('challenge_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('purpose', 20);
            $table->string('code_hash');
            $table->string('destination_masked', 60);
            $table->timestampTz('expires_at')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampTz('last_sent_at');
            $table->ipAddress('ip_address')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'purpose', 'created_at']);
        });

        DB::statement("ALTER TABLE otp_codes ADD CONSTRAINT otp_codes_channel_check CHECK (channel IN ('email', 'sms'))");
        DB::statement("ALTER TABLE otp_codes ADD CONSTRAINT otp_codes_purpose_check CHECK (purpose IN ('login', 'verify_email', 'verify_phone', 'password_reset', 'email_change'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
