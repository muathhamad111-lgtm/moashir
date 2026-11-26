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
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12);
            $table->string('channel', 10);
            $table->boolean('is_enabled');
            $table->timestampsTz();

            $table->unique(['user_id', 'code', 'channel']);
        });

        DB::statement("ALTER TABLE notification_preferences ADD CONSTRAINT notification_preferences_channel_check CHECK (channel IN ('email', 'sms'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
