<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Gender of staff accounts (personal data under PDPL: never written to the audit log in clear). Nullable for the
 * accounts that existed before; staff complete it in the account completion modal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('gender', 10)->nullable();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_gender_check CHECK (gender IS NULL OR gender IN ('male', 'female'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_gender_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('gender');
        });
    }
};
