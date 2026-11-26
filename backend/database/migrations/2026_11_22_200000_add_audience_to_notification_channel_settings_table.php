<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Channel choices become per audience (beneficiary / management). Every existing choice applied to both
 * audiences, so it is copied to each; going back keeps the beneficiary copy as the type-wide choice.
 *
 * Edited after it ran on staging (DEPLOY #50, 2026-11-22): the copy now runs after unique(code, channel) is
 * dropped, because copying first broke that key on production rows. Staging ran it on an empty table, so its end
 * schema is the same as this order produces.
 */
return new class extends Migration
{
    private const string CACHE_KEY = 'notifications.channel-settings';

    private const string TABLE = 'notification_channel_settings';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string('audience', 16)->default('beneficiary');
            $table->dropUnique(['code', 'channel']);
        });

        DB::statement("INSERT INTO notification_channel_settings (code, channel, audience, is_enabled, updated_by_user_id, created_at, updated_at)
            SELECT code, channel, 'management', is_enabled, updated_by_user_id, created_at, updated_at FROM notification_channel_settings WHERE audience = 'beneficiary'");

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(['code', 'channel', 'audience']);
        });

        DB::statement("ALTER TABLE notification_channel_settings ADD CONSTRAINT notification_channel_settings_audience_check CHECK (audience IN ('beneficiary', 'management'))");

        Cache::forget(self::CACHE_KEY);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notification_channel_settings DROP CONSTRAINT IF EXISTS notification_channel_settings_audience_check');
        DB::table(self::TABLE)->where('audience', 'management')->delete();

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(['code', 'channel', 'audience']);
            $table->unique(['code', 'channel']);
            $table->dropColumn('audience');
        });

        Cache::forget(self::CACHE_KEY);
    }
};
