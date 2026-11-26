<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array MESSAGE_CHECKS = [
        'reference' => "reference ~ '^CNT-[0-9]{4}-[0-9]{6}$'",
        'status' => "status IN ('new', 'in_progress', 'replied', 'closed')",
        'enquiry_type' => "enquiry_type IN ('general', 'self_assessment', 'accredited_assessment', 'payments', 'technical_support', 'other')",
        'locale' => "locale IN ('ar', 'en')",
        'closed' => "(status = 'closed') = (closed_at IS NOT NULL)",
        'linked' => 'entity_id IS NULL OR user_id IS NOT NULL',
        'sender_name' => 'char_length(btrim(sender_name)) BETWEEN 1 AND 150',
    ];

    private const array ENTRY_CHECKS = [
        'kind' => "kind IN ('message', 'reply', 'note')",
        'body' => 'char_length(body) BETWEEN 1 AND 5000',
    ];

    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->string('reference', 20)->unique();
            $table->string('status', 12);
            $table->string('enquiry_type', 30)->index();
            $table->string('sender_name', 150);
            $table->string('sender_email', 254)->index();
            $table->text('sender_mobile')->nullable();
            $table->string('organization_name', 200)->nullable();
            $table->string('locale', 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('privacy_terms_version_id')->nullable()->constrained('terms_versions')->nullOnDelete();
            $table->timestampTz('privacy_consent_at');
            $table->timestampTz('last_activity_at');
            $table->timestampTz('replied_at')->nullable();
            $table->timestampTz('closed_at')->nullable()->index();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'last_activity_at']);
            $table->index(['user_id', 'last_activity_at']);
            $table->index('created_at');
        });

        Schema::create('contact_message_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->text('body');
            $table->foreignId('author_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['contact_message_id', 'id']);
        });

        $this->addChecks('contact_messages', self::MESSAGE_CHECKS);
        $this->addChecks('contact_message_entries', self::ENTRY_CHECKS);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER contact_message_entries_no_update BEFORE UPDATE ON contact_message_entries
                FOR EACH ROW EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS contact_message_entries_no_update ON contact_message_entries');
        Schema::dropIfExists('contact_message_entries');
        Schema::dropIfExists('contact_messages');
    }

    /**
     * @param  array<string, string>  $checks
     */
    private function addChecks(string $table, array $checks): void
    {
        foreach ($checks as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE %1$s ADD CONSTRAINT %1$s_%2$s_check CHECK (%3$s)', $table, $name, $expression));
        }
    }
};
