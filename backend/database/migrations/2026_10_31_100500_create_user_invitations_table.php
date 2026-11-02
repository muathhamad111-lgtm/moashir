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
        Schema::create('user_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('status', 10)->default('pending');
            $table->foreignId('invited_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE user_invitations ADD CONSTRAINT user_invitations_status_check CHECK (status IN ('pending', 'accepted', 'expired', 'revoked'))");
        DB::statement("CREATE UNIQUE INDEX user_invitations_one_pending ON user_invitations (user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
