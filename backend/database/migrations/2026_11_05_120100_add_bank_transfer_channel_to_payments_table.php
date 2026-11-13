<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array PREVIOUS_CHECKS = [
        'provider' => "provider IN ('moyasar')",
        'status' => "status IN ('created', 'pending', 'paid', 'failed', 'cancelled', 'refunded')",
        'paid' => "status NOT IN ('paid', 'refunded') OR (paid_at IS NOT NULL AND provider_payment_id IS NOT NULL)",
    ];

    private const array CURRENT_CHECKS = [
        'provider' => "(channel = 'electronic' AND provider IS NOT DISTINCT FROM 'moyasar') OR (channel = 'bank_transfer' AND provider IS NULL)",
        'status' => "status IN ('created', 'pending', 'under_review', 'paid', 'failed', 'rejected', 'cancelled', 'refunded')",
        'paid' => "status NOT IN ('paid', 'refunded') OR (paid_at IS NOT NULL AND (provider_payment_id IS NOT NULL OR channel = 'bank_transfer'))",
        'channel' => "channel IN ('electronic', 'bank_transfer')",
        'electronic_status' => "channel = 'bank_transfer' OR status NOT IN ('under_review', 'rejected')",
        'transfer_status' => "channel = 'electronic' OR status IN ('under_review', 'paid', 'rejected', 'cancelled', 'refunded')",
        'gateway_columns' => "channel = 'electronic' OR (provider_intent_id IS NULL AND checkout_client_secret IS NULL AND provider_payment_id IS NULL AND method IS NULL AND card_last4 IS NULL)",
        'expires_at' => "(channel = 'electronic') = (expires_at IS NOT NULL)",
        'rejected' => "status <> 'rejected' OR rejected_at IS NOT NULL",
    ];

    private const string PREVIOUS_OPEN_ORDER = "CREATE UNIQUE INDEX payments_one_open_order ON payments (entity_id, purpose) WHERE status IN ('created', 'pending')";

    private const string CURRENT_OPEN_ORDER = "CREATE UNIQUE INDEX payments_one_open_order ON payments (entity_id, purpose) WHERE status IN ('created', 'pending', 'under_review')";

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('channel', 15)->default('electronic');
            $table->timestampTz('rejected_at')->nullable();
            $table->index(['channel', 'status', 'created_at']);
        });

        DB::statement('ALTER TABLE payments ALTER COLUMN provider DROP NOT NULL');
        DB::statement('ALTER TABLE payments ALTER COLUMN expires_at DROP NOT NULL');
        $this->replaceChecks(array_keys(self::PREVIOUS_CHECKS), self::CURRENT_CHECKS);
        $this->replaceOpenOrderIndex(self::CURRENT_OPEN_ORDER);
    }

    public function down(): void
    {
        if (DB::table('payments')->where('channel', 'bank_transfer')->exists()) {
            throw new RuntimeException('Bank-transfer payments exist; this migration cannot be rolled back without losing payment records.');
        }

        $this->replaceOpenOrderIndex(self::PREVIOUS_OPEN_ORDER);
        $this->replaceChecks(array_keys(self::CURRENT_CHECKS), self::PREVIOUS_CHECKS);
        DB::statement('ALTER TABLE payments ALTER COLUMN expires_at SET NOT NULL');
        DB::statement('ALTER TABLE payments ALTER COLUMN provider SET NOT NULL');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['channel', 'status', 'created_at']);
            $table->dropColumn(['channel', 'rejected_at']);
        });
    }

    /**
     * @param  list<string>  $dropped
     * @param  array<string, string>  $added
     */
    private function replaceChecks(array $dropped, array $added): void
    {
        foreach ($dropped as $name) {
            DB::statement(sprintf('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_%s_check', $name));
        }

        foreach ($added as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE payments ADD CONSTRAINT payments_%s_check CHECK (%s)', $name, $expression));
        }
    }

    private function replaceOpenOrderIndex(string $definition): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_one_open_order');
        DB::statement($definition);
    }
};
