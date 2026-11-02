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
        Schema::create('integration_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('provider', 20);
            $table->string('mode', 5)->default('live');
            $table->jsonb('public_config')->default('{}');
            $table->text('secrets')->nullable();
            $table->jsonb('secret_meta')->default('{}');
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('config_version')->default(1);
            $table->timestampTz('last_test_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_error_kind', 30)->nullable();
            $table->unsignedInteger('last_test_latency_ms')->nullable();
            $table->foreignId('last_test_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->jsonb('provider_refs')->default('{}');
            $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['kind', 'provider', 'mode']);
        });

        DB::statement('CREATE UNIQUE INDEX integration_credentials_one_active_per_kind ON integration_credentials (kind) WHERE is_active');
        DB::statement("ALTER TABLE integration_credentials ADD CONSTRAINT integration_credentials_kind_check CHECK (kind IN ('payment', 'sms', 'email', 'ai'))");
        DB::statement("ALTER TABLE integration_credentials ADD CONSTRAINT integration_credentials_provider_check CHECK (
            (kind = 'payment' AND provider = 'moyasar')
            OR (kind = 'sms' AND provider IN ('taqnyat', 'msegat'))
            OR (kind = 'email' AND provider = 'smtp')
            OR (kind = 'ai' AND provider IN ('openai', 'anthropic'))
        )");
        DB::statement("ALTER TABLE integration_credentials ADD CONSTRAINT integration_credentials_mode_check CHECK (mode IN ('test', 'live'))");
        DB::statement("ALTER TABLE integration_credentials ADD CONSTRAINT integration_credentials_json_check CHECK (
            jsonb_typeof(public_config) = 'object' AND jsonb_typeof(secret_meta) = 'object' AND jsonb_typeof(provider_refs) = 'object'
        )");
        DB::statement('ALTER TABLE integration_credentials ADD CONSTRAINT integration_credentials_version_check CHECK (config_version >= 1)');
        DB::statement("ALTER TABLE integration_credentials ADD CONSTRAINT integration_credentials_error_kind_check CHECK (
            last_test_error_kind IS NULL OR last_test_error_kind IN (
                'auth_failed', 'account_inactive', 'misconfigured', 'recipient_invalid', 'rejected', 'quota_exhausted',
                'rate_limited', 'unavailable', 'timeout', 'request_too_large', 'unknown'
            )
        )");
        DB::statement('ALTER TABLE integration_credentials ADD CONSTRAINT integration_credentials_test_result_check CHECK (
            (last_test_at IS NULL AND last_test_ok IS NULL) OR (last_test_at IS NOT NULL AND last_test_ok IS NOT NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
    }
};
