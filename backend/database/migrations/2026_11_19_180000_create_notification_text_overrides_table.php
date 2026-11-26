<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string CACHE_KEY = 'notifications.text-overrides';

    public function up(): void
    {
        Schema::create('notification_text_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key', 191)->unique();
            $table->string('code', 16)->index();
            $table->jsonb('parts');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE notification_text_overrides ADD CONSTRAINT notification_text_overrides_parts_check CHECK (jsonb_typeof(parts) = 'object')");

        Cache::forget(self::CACHE_KEY);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_text_overrides');
        Cache::forget(self::CACHE_KEY);
    }
};
