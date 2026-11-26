<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An agreement acceptance records the roles the user held when accepting, so the append-only row is the whole
 * evidence. The append-only trigger only rejects UPDATE, DELETE and TRUNCATE, so adding a nullable column and
 * inserting rows with it are unaffected. Terms and privacy acceptances leave it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms_acceptances', function (Blueprint $table): void {
            $table->jsonb('accepted_roles')->nullable();
        });

        DB::statement("ALTER TABLE terms_acceptances ADD CONSTRAINT terms_acceptances_accepted_roles_array CHECK (accepted_roles IS NULL OR jsonb_typeof(accepted_roles) = 'array')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE terms_acceptances DROP CONSTRAINT terms_acceptances_accepted_roles_array');

        Schema::table('terms_acceptances', function (Blueprint $table): void {
            $table->dropColumn('accepted_roles');
        });
    }
};
