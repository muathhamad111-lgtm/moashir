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
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12);
            $table->string('category', 20);
            $table->jsonb('title');
            $table->jsonb('body');
            $table->string('link', 300)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('actioned_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index(['user_id', 'category']);
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_category_check CHECK (category IN ('action_required', 'informational', 'alert', 'error'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
