<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The framework broker keyed reset tokens by e-mail, so the reset form had to ask for it.
 * A link is now found by the keyed hash of its token alone; links issued before this migration stop working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::dropIfExists('password_reset_links');
    }
};
