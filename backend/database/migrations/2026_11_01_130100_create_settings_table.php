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
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 30)->index();
            $table->string('key', 80)->unique();
            $table->string('type', 15);
            $table->jsonb('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE settings ADD CONSTRAINT settings_type_check CHECK (type IN ('string', 'integer', 'decimal', 'boolean', 'json', 'select'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
