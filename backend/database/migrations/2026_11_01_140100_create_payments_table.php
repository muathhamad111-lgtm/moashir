<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'purpose' => "purpose IN ('accredited', 'bag')",
        'provider' => "provider IN ('moyasar')",
        'mode' => "mode IN ('test', 'live')",
        'status' => "status IN ('created', 'pending', 'paid', 'failed', 'cancelled', 'refunded')",
        'method' => "method IS NULL OR method IN ('creditcard', 'mada', 'applepay', 'stcpay')",
        'reference' => "reference ~ '^PAY-[0-9]{4}-[0-9]{6}$'",
        'currency' => "currency ~ '^[A-Z]{3}$'",
        'price' => 'price_before_vat > 0',
        'vat_rate' => 'vat_rate BETWEEN 0 AND 100',
        'vat_amount' => 'vat_amount >= 0',
        'total' => 'total = price_before_vat + vat_amount',
        'card_last4' => "card_last4 IS NULL OR card_last4 ~ '^[0-9]{4}$'",
        'entity_name' => "jsonb_typeof(entity_name) = 'object'",
        'failure_reason' => "failure_reason IS NULL OR failure_reason IN ('insufficient_funds', 'declined', 'expired_card', 'lost_or_stolen', 'limit_exceeded', 'amount_mismatch', 'checkout_unavailable', 'settled_manually', 'other')",
        'pending_bound' => "status <> 'pending' OR provider_payment_id IS NOT NULL",
        'paid' => "status NOT IN ('paid', 'refunded') OR (paid_at IS NOT NULL AND provider_payment_id IS NOT NULL)",
        'failed' => "status <> 'failed' OR (failed_at IS NOT NULL AND failure_reason IS NOT NULL)",
        'cancelled' => "status <> 'cancelled' OR cancelled_at IS NOT NULL",
        'refunded' => "status <> 'refunded' OR refunded_at IS NOT NULL",
        'late_paid' => "late_paid_at IS NULL OR status = 'cancelled'",
        'client_secret' => "checkout_client_secret IS NULL OR status IN ('created', 'pending')",
        'bound' => '(provider_payment_id IS NULL) = (bound_at IS NULL)',
    ];

    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->string('reference', 20)->unique();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->jsonb('entity_name');
            $table->foreignId('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('purpose', 20)->index();
            $table->string('provider', 20)->default('moyasar');
            $table->string('mode', 5);
            $table->uuid('idempotency_key');
            $table->string('provider_intent_id', 80)->nullable()->unique();
            $table->text('checkout_client_secret')->nullable();
            $table->string('provider_payment_id', 80)->nullable()->unique();
            $table->string('method', 15)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->decimal('price_before_vat', 12, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->char('currency', 3)->default('SAR');
            $table->string('status', 20);
            $table->timestampTz('expires_at');
            $table->timestampTz('bound_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampTz('late_paid_at')->nullable();
            $table->timestampTz('manually_settled_at')->nullable();
            $table->string('failure_reason', 30)->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['initiated_by_user_id', 'idempotency_key']);
            $table->index(['entity_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['mode', 'status', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE payments ADD CONSTRAINT payments_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX payments_one_open_order ON payments (entity_id, purpose) WHERE status IN ('created', 'pending')");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
