<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'purpose' => "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export')",
        'visibility' => "visibility IN ('private', 'public_asset')",
        'content_class' => "content_class IN ('P', 'I', 'C', 'S')",
        'scan_status' => "malware_scan_status IN ('pending', 'clean', 'infected', 'failed')",
        'scanned_at' => "malware_scan_status = 'pending' OR malware_scanned_at IS NOT NULL",
        'size' => 'size_bytes >= 0',
        'sha256' => "sha256 ~ '^[0-9a-f]{64}$'",
    ];

    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->string('purpose', 40);
            $table->string('disk', 30);
            $table->string('path', 255)->unique();
            $table->string('original_name', 255);
            $table->string('extension', 10)->nullable();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->string('visibility', 15);
            $table->char('content_class', 1);
            $table->string('malware_scan_status', 10);
            $table->timestampTz('malware_scanned_at')->nullable();
            $table->string('scan_engine', 60)->nullable();
            $table->nullableMorphs('attachable');
            $table->foreignId('uploaded_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->boolean('is_encrypted_at_rest')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['purpose', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE stored_files ADD CONSTRAINT stored_files_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE INDEX stored_files_unscanned ON stored_files (malware_scan_status) WHERE malware_scan_status <> 'clean'");
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
