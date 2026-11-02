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
        Schema::create('award_cycle_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['award_cycle_id', 'user_id', 'role']);
        });

        DB::statement("ALTER TABLE award_cycle_members ADD CONSTRAINT award_cycle_members_role_check CHECK (role IN ('filtering_officer', 'award_secretary'))");

        Schema::create('award_cycle_assessors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessor_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['award_cycle_id', 'assessor_user_id']);
        });

        Schema::create('award_cycle_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->unsignedInteger('recipients_count');
            $table->timestampTz('sent_at');

            $table->unique(['award_cycle_id', 'days_before']);
        });

        DB::statement('ALTER TABLE award_cycle_reminders ADD CONSTRAINT award_cycle_reminders_days_check CHECK (days_before > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('award_cycle_reminders');
        Schema::dropIfExists('award_cycle_assessors');
        Schema::dropIfExists('award_cycle_members');
    }
};
