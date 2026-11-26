<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Operator decision 2026-11-14: the data-subject requests feature is removed completely —
 * the request register, its permission and the «طلب بيانات شخصية» contact enquiry type.
 */
return new class extends Migration
{
    private const string PERMISSION = 'privacy.requests.manage';

    private const string REMOVED_TYPE = 'privacy_request';

    private const string FALLBACK_TYPE = 'other';

    private const array REMAINING_TYPES = ['general', 'self_assessment', 'accredited_assessment', 'payments', 'technical_support', 'other'];

    public function up(): void
    {
        DB::table('contact_messages')->where('enquiry_type', self::REMOVED_TYPE)->update(['enquiry_type' => self::FALLBACK_TYPE]);
        $this->replaceEnquiryTypeCheck(self::REMAINING_TYPES);

        Schema::dropIfExists('privacy_requests');

        DB::table('permissions')->where('name', self::PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Restores the schema only: the dropped requests, the permission grants and the original
     * enquiry type of the remapped messages are not recoverable.
     */
    public function down(): void
    {
        $this->replaceEnquiryTypeCheck([...self::REMAINING_TYPES, self::REMOVED_TYPE]);
        $this->createRequestsTable();
    }

    /**
     * @param  list<string>  $types
     */
    private function replaceEnquiryTypeCheck(array $types): void
    {
        $quoted = implode(', ', array_map(static fn (string $type): string => "'".$type."'", $types));

        DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_enquiry_type_check');
        DB::statement(sprintf('ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_enquiry_type_check CHECK (enquiry_type IN (%s))', $quoted));
    }

    private function createRequestsTable(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->index()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20)->default('open');
            $table->text('details');
            $table->foreignId('created_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_kind_check CHECK (kind IN ('access', 'correction', 'deletion'))");
        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_status_check CHECK (status IN ('open', 'fulfilled', 'rejected'))");
        DB::statement("ALTER TABLE privacy_requests ADD CONSTRAINT privacy_requests_decision_check CHECK (status = 'open' OR (decided_at IS NOT NULL AND decided_by_user_id IS NOT NULL))");
    }
};
