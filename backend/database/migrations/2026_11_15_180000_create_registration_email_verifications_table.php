<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('challenge_id')->unique();
            $table->text('email');
            $table->char('email_index', 64)->index();
            $table->string('code_hash');
            $table->timestampTz('expires_at')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampTz('last_sent_at');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestampTz('token_expires_at')->nullable();
            $table->timestampTz('token_used_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_email_verifications');
    }
};
