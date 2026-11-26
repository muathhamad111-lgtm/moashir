<?php

declare(strict_types=1);

use App\Models\Identity\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string USER_MODEL = User::class;

    private const string SCORING_PERMISSION = 'assessment.score.manage';

    public function up(): void
    {
        Schema::create('user_assessor_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('qualification', 30);
            $table->foreignId('granted_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'qualification']);
            $table->index('qualification');
        });

        DB::statement("ALTER TABLE user_assessor_qualifications ADD CONSTRAINT user_assessor_qualifications_qualification_check CHECK (qualification IN ('accredited', 'advanced_initial'))");

        $this->qualifyCurrentAssessorsForAccredited();
        $this->qualifyLinkedInitialAssessorsForAdvanced();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_assessor_qualifications');
    }

    private function qualifyCurrentAssessorsForAccredited(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO user_assessor_qualifications (user_id, qualification, created_at, updated_at)
            SELECT DISTINCT users.id, 'accredited', now(), now()
            FROM users
            WHERE users.entity_id IS NULL
              AND users.is_super_admin = false
              AND (
                users.id IN (
                    SELECT model_has_roles.model_id
                    FROM model_has_roles
                    JOIN role_has_permissions ON role_has_permissions.role_id = model_has_roles.role_id
                    JOIN permissions ON permissions.id = role_has_permissions.permission_id
                    WHERE model_has_roles.model_type = ? AND permissions.name = ?
                )
                OR users.id IN (
                    SELECT model_has_permissions.model_id
                    FROM model_has_permissions
                    JOIN permissions ON permissions.id = model_has_permissions.permission_id
                    WHERE model_has_permissions.model_type = ? AND permissions.name = ?
                )
              )
            ON CONFLICT (user_id, qualification) DO NOTHING
            SQL, [self::USER_MODEL, self::SCORING_PERMISSION, self::USER_MODEL, self::SCORING_PERMISSION]);
    }

    private function qualifyLinkedInitialAssessorsForAdvanced(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO user_assessor_qualifications (user_id, qualification, created_at, updated_at)
            SELECT DISTINCT award_cycle_assessors.assessor_user_id, 'advanced_initial', now(), now()
            FROM award_cycle_assessors
            WHERE award_cycle_assessors.is_active = true
            ON CONFLICT (user_id, qualification) DO NOTHING
            SQL);
    }
};
