<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string PREVIOUS_PURPOSES = "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle', 'cms_image', 'kpi_image', 'branding_logo', 'certificate_pdf', 'entity_logo', 'transfer_proof', 'user_avatar', 'vat_certificate', 'office_rendition', 'cms_logo')";

    private const string CURRENT_PURPOSES = "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle', 'cms_image', 'kpi_image', 'branding_logo', 'certificate_pdf', 'entity_logo', 'transfer_proof', 'user_avatar', 'vat_certificate', 'office_rendition', 'cms_logo', 'entity_license_certificate', 'entity_authorization_letter')";

    private const array DOCUMENTS = ['license_certificate', 'authorization_letter'];

    public function up(): void
    {
        $this->replacePurposeCheck(self::CURRENT_PURPOSES);

        Schema::table('entities', function (Blueprint $table): void {
            foreach (self::DOCUMENTS as $document) {
                $table->foreignId($document.'_file_id')->nullable()->index()->constrained('stored_files')->restrictOnDelete();
            }
        });

        Schema::table('entity_profile_change_requests', function (Blueprint $table): void {
            foreach (self::DOCUMENTS as $document) {
                $table->foreignId('pending_'.$document.'_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
                $table->boolean('removes_'.$document)->default(false);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('stored_files')->whereIn('purpose', ['entity_license_certificate', 'entity_authorization_letter'])->exists()) {
            throw new RuntimeException('Entity license certificates or authorization letters are stored; remove them before rolling this migration back.');
        }

        Schema::table('entity_profile_change_requests', function (Blueprint $table): void {
            foreach (self::DOCUMENTS as $document) {
                $table->dropConstrainedForeignId('pending_'.$document.'_file_id');
                $table->dropColumn('removes_'.$document);
            }
        });

        Schema::table('entities', function (Blueprint $table): void {
            foreach (self::DOCUMENTS as $document) {
                $table->dropConstrainedForeignId($document.'_file_id');
            }
        });

        $this->replacePurposeCheck(self::PREVIOUS_PURPOSES);
    }

    private function replacePurposeCheck(string $expression): void
    {
        DB::statement('ALTER TABLE stored_files DROP CONSTRAINT IF EXISTS stored_files_purpose_check');
        DB::statement(sprintf('ALTER TABLE stored_files ADD CONSTRAINT stored_files_purpose_check CHECK (%s)', $expression));
    }
};
