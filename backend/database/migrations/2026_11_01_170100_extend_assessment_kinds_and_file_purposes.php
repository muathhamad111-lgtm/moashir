<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array CHECKS = [
        'assessment_scores' => [
            'assessment_scores_scorer_role_check',
            "scorer_role IN ('entity')",
            "scorer_role IN ('entity', 'assessor')",
        ],
        'assessment_result_snapshots' => [
            'assessment_result_snapshots_kind_check',
            "kind IN ('self_result')",
            "kind IN ('self_result', 'accredited_result', 'override_recompute')",
        ],
        'stored_files' => [
            'stored_files_purpose_check',
            "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export')",
            "purpose IN ('refund_evidence', 'apple_pay_domain_association', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle')",
        ],
    ];

    public function up(): void
    {
        foreach (self::CHECKS as $table => [$name, , $expression]) {
            $this->replace($table, $name, $expression);
        }
    }

    public function down(): void
    {
        foreach (self::CHECKS as $table => [$name, $expression]) {
            $this->replace($table, $name, $expression);
        }
    }

    private function replace(string $table, string $name, string $expression): void
    {
        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $expression));
    }
};
