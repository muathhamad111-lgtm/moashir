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
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('path', 20)->nullable()->after('link');
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_path_check CHECK (path IS NULL OR path IN ('self', 'accredited', 'advanced'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_path_check');

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('path');
        });
    }
};
