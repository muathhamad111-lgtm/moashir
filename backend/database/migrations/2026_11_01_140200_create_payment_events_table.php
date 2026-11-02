<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'provider' => "provider IN ('moyasar')",
        'source' => "source IN ('checkout', 'verification', 'webhook', 'expiry', 'reconciliation', 'admin')",
        'mode' => "mode IS NULL OR mode IN ('test', 'live')",
        'result' => "processing_result IS NULL OR processing_result IN ('applied', 'pending', 'ignored', 'rejected', 'unmatched', 'late_payment')",
        'processed' => '(processed_at IS NULL) = (processing_result IS NULL)',
        'payload' => "jsonb_typeof(payload) = 'object'",
    ];

    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('provider_event_id', 120);
            $table->string('source', 20);
            $table->string('event_type', 40);
            $table->string('mode', 5)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('provider_payment_id', 80)->nullable()->index();
            $table->jsonb('payload');
            $table->ipAddress('ip_address')->nullable();
            $table->foreignId('actor_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('processed_at')->nullable();
            $table->string('processing_result', 20)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestampTz('created_at');

            $table->unique(['provider', 'provider_event_id']);
            $table->index(['payment_id', 'created_at']);
            $table->index(['source', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE payment_events ADD CONSTRAINT payment_events_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement('CREATE INDEX payment_events_unprocessed ON payment_events (created_at) WHERE processed_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_payment_events() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND OLD.processed_at IS NULL
                    AND (OLD.payment_id IS NULL OR NEW.payment_id IS NOT DISTINCT FROM OLD.payment_id)
                    AND (to_jsonb(NEW) - 'processed_at' - 'processing_result' - 'error_code' - 'payment_id')
                        = (to_jsonb(OLD) - 'processed_at' - 'processing_result' - 'error_code' - 'payment_id') THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payment_events_append_only BEFORE UPDATE OR DELETE ON payment_events
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_payment_events();
            CREATE TRIGGER payment_events_append_only_truncate BEFORE TRUNCATE ON payment_events
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS payment_events_append_only_truncate ON payment_events');
        DB::unprepared('DROP TRIGGER IF EXISTS payment_events_append_only ON payment_events');
        Schema::dropIfExists('payment_events');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_payment_events()');
    }
};
