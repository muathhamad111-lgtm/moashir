<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'code' => "code IN ('PER-01', 'PER-02', 'PER-03', 'PER-05')",
        'threshold' => 'threshold_days >= 0',
    ];

    public function up(): void
    {
        Schema::create('period_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('entity_id')->index()->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->unsignedSmallInteger('threshold_days')->default(0);
            $table->timestampTz('sent_at');

            $table->unique(['assessment_period_id', 'code', 'threshold_days', 'entity_id']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE period_notices ADD CONSTRAINT period_notices_%s_check CHECK (%s)', $name, $expression));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('period_notices');
    }
};
