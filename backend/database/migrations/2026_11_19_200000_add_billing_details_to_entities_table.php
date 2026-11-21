<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string PREVIOUS_PURPOSES = "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle', 'cms_image', 'kpi_image', 'branding_logo', 'certificate_pdf', 'entity_logo', 'transfer_proof', 'user_avatar')";

    private const string CURRENT_PURPOSES = "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle', 'cms_image', 'kpi_image', 'branding_logo', 'certificate_pdf', 'entity_logo', 'transfer_proof', 'user_avatar', 'vat_certificate')";

    private const array CHECKS = [
        'address_building_number' => "address_building_number IS NULL OR address_building_number ~ '^[0-9]{4}$'",
        'address_postal_code' => "address_postal_code IS NULL OR address_postal_code ~ '^[0-9]{5}$'",
        'address_additional_number' => "address_additional_number IS NULL OR address_additional_number ~ '^[0-9]{4}$'",
        'address_short_code' => "address_short_code IS NULL OR address_short_code ~ '^[A-Z]{4}[0-9]{4}$'",
        'vat_number' => "vat_number IS NULL OR (vat_registered IS TRUE AND vat_number ~ '^3[0-9]{13}3$')",
        'vat_certificate_file_id' => 'vat_certificate_file_id IS NULL OR vat_registered IS TRUE',
    ];

    public function up(): void
    {
        $this->replacePurposeCheck(self::CURRENT_PURPOSES);

        Schema::table('entities', function (Blueprint $table): void {
            $table->string('address_building_number', 4)->nullable();
            $table->string('address_street', 150)->nullable();
            $table->string('address_district', 150)->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_postal_code', 5)->nullable();
            $table->string('address_additional_number', 4)->nullable();
            $table->string('address_short_code', 8)->nullable();
            $table->boolean('vat_registered')->nullable();
            $table->string('vat_number', 15)->nullable();
            $table->foreignId('vat_certificate_file_id')->nullable()->index()->constrained('stored_files')->restrictOnDelete();
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE entities ADD CONSTRAINT entities_%s_check CHECK (%s)', $name, $expression));
        }
    }

    public function down(): void
    {
        if (DB::table('stored_files')->where('purpose', 'vat_certificate')->exists()) {
            throw new RuntimeException('VAT registration certificates are stored; remove them before rolling this migration back.');
        }

        foreach (array_keys(self::CHECKS) as $name) {
            DB::statement(sprintf('ALTER TABLE entities DROP CONSTRAINT IF EXISTS entities_%s_check', $name));
        }

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vat_certificate_file_id');
            $table->dropColumn([
                'address_building_number', 'address_street', 'address_district', 'address_city', 'address_postal_code',
                'address_additional_number', 'address_short_code', 'vat_registered', 'vat_number',
            ]);
        });

        $this->replacePurposeCheck(self::PREVIOUS_PURPOSES);
    }

    private function replacePurposeCheck(string $expression): void
    {
        DB::statement('ALTER TABLE stored_files DROP CONSTRAINT IF EXISTS stored_files_purpose_check');
        DB::statement(sprintf('ALTER TABLE stored_files ADD CONSTRAINT stored_files_purpose_check CHECK (%s)', $expression));
    }
};
