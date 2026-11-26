<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->timestampTz('archived_at')->nullable()->after('actioned_at');

            $table->index(['user_id', 'archived_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'archived_at', 'created_at']);
            $table->dropColumn('archived_at');
        });
    }
};
