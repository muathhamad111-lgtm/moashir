<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each reminder round sent for an agreement version. The partial unique index makes the scheduled reminder
 * (NDA-02) run once per version even when two schedulers overlap; manual rounds are limited by the application to
 * one per 24 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nda_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terms_version_id')->constrained()->restrictOnDelete();
            $table->string('source', 10);
            $table->unsignedInteger('recipients')->default(0);
            $table->timestampTz('created_at')->useCurrent()->index();

            $table->index(['terms_version_id', 'created_at']);
        });

        DB::statement("ALTER TABLE nda_reminders ADD CONSTRAINT nda_reminders_source_check CHECK (source IN ('auto', 'manual'))");
        DB::statement("CREATE UNIQUE INDEX nda_reminders_one_auto_per_version ON nda_reminders (terms_version_id) WHERE source = 'auto'");
    }

    public function down(): void
    {
        Schema::dropIfExists('nda_reminders');
    }
};
