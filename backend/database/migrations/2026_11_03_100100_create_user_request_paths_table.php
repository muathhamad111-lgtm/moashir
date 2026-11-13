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
        Schema::create('user_request_paths', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path', 20);
            $table->foreignId('granted_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'path']);
        });

        DB::statement("ALTER TABLE user_request_paths ADD CONSTRAINT user_request_paths_path_check CHECK (path IN ('accredited', 'advanced'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_request_paths');
    }
};
