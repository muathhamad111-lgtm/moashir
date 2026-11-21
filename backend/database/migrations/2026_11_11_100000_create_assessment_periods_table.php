<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'cadence' => "cadence IN ('quarterly', 'semi_annual', 'annual')",
        'dates' => 'ends_on >= starts_on AND ends_at > starts_at',
        'sequence' => 'sequence BETWEEN 1 AND 4',
        'reminders' => "jsonb_typeof(reminders_sent) = 'array'",
    ];

    public function up(): void
    {
        Schema::create('assessment_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('cadence', 20);
            $table->string('key', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('sequence');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('is_shortened')->default(false);
            $table->timestampTz('notice_sent_at')->nullable();
            $table->jsonb('reminders_sent')->default(DB::raw("'[]'::jsonb"));
            $table->date('last_digest_on')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->index(['starts_on', 'ends_on']);
            $table->index(['starts_at', 'ends_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_periods ADD CONSTRAINT assessment_periods_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement('CREATE UNIQUE INDEX assessment_periods_live_key ON assessment_periods (key) WHERE superseded_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assessment_periods_live_start ON assessment_periods (starts_on) WHERE superseded_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_periods');
    }
};
