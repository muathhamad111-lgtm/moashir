<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string PREVIOUS = "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle', 'cms_image', 'kpi_image', 'branding_logo')";

    private const string CURRENT = "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle', 'cms_image', 'kpi_image', 'branding_logo', 'certificate_pdf')";

    public function up(): void
    {
        $this->replaceCheck(self::CURRENT);
    }

    public function down(): void
    {
        $this->replaceCheck(self::PREVIOUS);
    }

    private function replaceCheck(string $expression): void
    {
        DB::statement('ALTER TABLE stored_files DROP CONSTRAINT IF EXISTS stored_files_purpose_check');
        DB::statement(sprintf('ALTER TABLE stored_files ADD CONSTRAINT stored_files_purpose_check CHECK (%s)', $expression));
    }
};
