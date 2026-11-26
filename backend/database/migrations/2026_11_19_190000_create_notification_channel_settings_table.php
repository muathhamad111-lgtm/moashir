<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string CACHE_KEY = 'notifications.channel-settings';

    public function up(): void
    {
        Schema::create('notification_channel_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16);
            $table->string('channel', 16);
            $table->boolean('is_enabled');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['code', 'channel']);
        });

        DB::statement("ALTER TABLE notification_channel_settings ADD CONSTRAINT notification_channel_settings_channel_check CHECK (channel IN ('in_app', 'email', 'sms'))");

        Cache::forget(self::CACHE_KEY);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channel_settings');
        Cache::forget(self::CACHE_KEY);
    }
};
