<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_themes', function (Blueprint $table): void {
            $table->foreignId('favicon_file_id')->nullable()->index()->constrained('stored_files')->nullOnDelete();
            $table->foreignId('site_logo_file_id')->nullable()->index()->constrained('stored_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branding_themes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('favicon_file_id');
            $table->dropConstrainedForeignId('site_logo_file_id');
        });
    }
};
