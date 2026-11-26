<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'iban' => "iban ~ '^SA[0-9]{2}[0-9]{2}[A-Z0-9]{18}$'",
        'account_number' => "account_number IS NULL OR account_number ~ '^[A-Za-z0-9]{6,30}$'",
        'bank_name' => "jsonb_typeof(bank_name) = 'object'",
        'account_holder_name' => "jsonb_typeof(account_holder_name) = 'object'",
        'payer_note' => "payer_note IS NULL OR jsonb_typeof(payer_note) = 'object'",
        'display_order' => 'display_order > 0',
        'active' => 'is_active = (deactivated_at IS NULL)',
        'archived' => 'archived_at IS NULL OR NOT is_active',
    ];

    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->jsonb('bank_name');
            $table->jsonb('account_holder_name');
            $table->char('iban', 24)->unique();
            $table->string('account_number', 30)->nullable();
            $table->jsonb('payer_note')->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->boolean('is_active');
            $table->timestampTz('activated_at');
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['archived_at', 'is_active', 'display_order']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE bank_accounts ADD CONSTRAINT bank_accounts_%s_check CHECK (%s)', $name, $expression));
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_bank_accounts() RETURNS trigger AS $$
            BEGIN
                IF NEW.public_id = OLD.public_id
                    AND NEW.iban = OLD.iban
                    AND NEW.bank_name = OLD.bank_name
                    AND NEW.account_holder_name = OLD.account_holder_name
                    AND NEW.account_number IS NOT DISTINCT FROM OLD.account_number
                    AND NEW.created_by_user_id = OLD.created_by_user_id
                    AND (OLD.archived_at IS NULL OR NEW.archived_at IS NOT DISTINCT FROM OLD.archived_at) THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'immutable_payee: % on % changes a payee field', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER bank_accounts_payee_guard BEFORE UPDATE ON bank_accounts
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_bank_accounts();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS bank_accounts_payee_guard ON bank_accounts');
        Schema::dropIfExists('bank_accounts');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_bank_accounts()');
    }
};
