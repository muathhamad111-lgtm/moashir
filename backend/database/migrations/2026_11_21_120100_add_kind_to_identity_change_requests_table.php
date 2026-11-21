<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A holder's first national ID now goes through the same approval as a correction. A first entry has no reason;
 * a correction keeps needing one (checked here as the last line of defence).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_change_requests', function (Blueprint $table): void {
            $table->string('kind', 20)->default('correction');
            $table->text('reason')->nullable()->change();
        });

        DB::statement("ALTER TABLE identity_change_requests ADD CONSTRAINT identity_change_requests_kind_check CHECK (kind IN ('first_entry', 'correction'))");
        DB::statement("ALTER TABLE identity_change_requests ADD CONSTRAINT identity_change_requests_reason_check CHECK (kind = 'first_entry' OR reason IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE identity_change_requests DROP CONSTRAINT IF EXISTS identity_change_requests_reason_check');
        DB::statement('ALTER TABLE identity_change_requests DROP CONSTRAINT IF EXISTS identity_change_requests_kind_check');
        DB::table('identity_change_requests')->whereNull('reason')->update(['reason' => '-']);

        Schema::table('identity_change_requests', function (Blueprint $table): void {
            $table->text('reason')->nullable(false)->change();
            $table->dropColumn('kind');
        });
    }
};
