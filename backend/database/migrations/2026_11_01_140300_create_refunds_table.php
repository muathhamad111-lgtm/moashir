<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'amount' => 'amount > 0',
        'currency' => "currency ~ '^[A-Z]{3}$'",
        'status' => "status IN ('recorded')",
        'transfer_reference' => 'char_length(transfer_reference) BETWEEN 3 AND 80',
        'reason' => 'char_length(reason) >= 20',
    ];

    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->date('refunded_on');
            $table->text('reason');
            $table->string('transfer_reference', 80);
            $table->foreignId('evidence_file_id')->unique()->constrained('stored_files')->restrictOnDelete();
            $table->string('status', 10);
            $table->foreignId('recorded_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['refunded_on']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE refunds ADD CONSTRAINT refunds_%s_check CHECK (%s)', $name, $expression));
        }

        DB::unprepared('CREATE TRIGGER refunds_append_only BEFORE UPDATE OR DELETE ON refunds FOR EACH ROW EXECUTE FUNCTION moashir_reject_append_only_change()');
        DB::unprepared('CREATE TRIGGER refunds_append_only_truncate BEFORE TRUNCATE ON refunds FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change()');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS refunds_append_only_truncate ON refunds');
        DB::unprepared('DROP TRIGGER IF EXISTS refunds_append_only ON refunds');
        Schema::dropIfExists('refunds');
    }
};
