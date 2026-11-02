<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'entity_type' => "entity_type IN ('civil_association', 'civil_foundation', 'waqf')",
        'development_sector' => "development_sector IN ('educational', 'social', 'health', 'developmental')",
        'org_size' => "org_size IN ('small', 'medium', 'large')",
        'status' => "status IN ('pending_verification', 'active', 'deactivated')",
        'founding_year' => 'founding_year BETWEEN 1900 AND 2100',
        'governance_grade' => 'governance_grade IS NULL OR governance_grade BETWEEN 0 AND 100',
        'employees_count' => 'employees_count IS NULL OR employees_count >= 0',
        'beneficiaries_count' => 'annual_beneficiaries_count IS NULL OR annual_beneficiaries_count >= 0',
        'name' => "jsonb_typeof(name) = 'object' AND coalesce(name->>'ar', '') <> ''",
        'erased_deleted' => 'anonymized_at IS NULL OR deleted_at IS NOT NULL',
        'deactivation' => "status <> 'deactivated' OR deactivated_at IS NOT NULL",
    ];

    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->jsonb('name');
            $table->string('entity_type', 30);
            $table->string('development_sector', 30);
            $table->string('org_size', 10);
            $table->unsignedSmallInteger('founding_year');
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('city_id');
            $table->decimal('governance_grade', 5, 2)->nullable();
            $table->unsignedInteger('employees_count')->nullable();
            $table->unsignedInteger('annual_beneficiaries_count')->nullable();
            $table->boolean('has_strategic_plan')->default(false);
            $table->string('status', 25)->default('pending_verification');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('profile_completed_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->timestampTz('marketing_consent_at')->nullable();
            $table->timestampTz('marketing_unsubscribed_at')->nullable();
            $table->timestampTz('verification_reminded_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->text('deletion_reason')->nullable();
            $table->timestampTz('restored_at')->nullable();
            $table->timestampTz('anonymized_at')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign(['city_id', 'region_id'])->references(['id', 'region_id'])->on('cities')->restrictOnDelete();
            $table->index('city_id');
            $table->index('status');
            $table->index(['entity_type', 'development_sector']);
            $table->index('founding_year');
            $table->index('deleted_at');
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE entities ADD CONSTRAINT entities_%s_check CHECK (%s)', $name, $expression));
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
        });

        Schema::table('email_change_requests', function (Blueprint $table): void {
            $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_change_requests', function (Blueprint $table): void {
            $table->dropForeign(['entity_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['entity_id']);
        });

        Schema::dropIfExists('entities');
    }
};
