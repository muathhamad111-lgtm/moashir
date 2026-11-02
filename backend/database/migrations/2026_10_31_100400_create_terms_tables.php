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
        Schema::create('terms_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 10);
            $table->string('version', 20);
            $table->jsonb('body');
            $table->date('effective_from');
            $table->boolean('is_current')->default(false);
            $table->foreignId('published_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['kind', 'version']);
        });

        DB::statement("ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_kind_check CHECK (kind IN ('terms', 'privacy'))");
        DB::statement("ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_body_object CHECK (jsonb_typeof(body) = 'object')");
        DB::statement('CREATE UNIQUE INDEX terms_versions_current_unique ON terms_versions (kind) WHERE is_current');

        Schema::create('terms_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('terms_version_id')->index()->constrained()->restrictOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'terms_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
        Schema::dropIfExists('terms_versions');
    }
};
