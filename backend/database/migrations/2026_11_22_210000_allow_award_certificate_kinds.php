<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Award certificates (operator, 2026-11-22): a winner, a qualified (non-winner) and a participation certificate
 * join the accredited one. Only the kind check widens; the immutability triggers compare the kind of a row with
 * itself and list no values, so they need no change.
 *
 * The rollback restores the accredited-only check while no award certificate exists; once one was issued it stops
 * with a clear message instead of deleting certificates.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE certificates DROP CONSTRAINT certificates_kind_check');
        DB::statement("ALTER TABLE certificates ADD CONSTRAINT certificates_kind_check CHECK (kind IN ('accredited', 'advanced_winner', 'advanced_qualified', 'advanced_participation'))");
    }

    public function down(): void
    {
        if (DB::table('certificates')->where('kind', '<>', 'accredited')->exists()) {
            throw new RuntimeException('Award certificates exist; the certificate kinds cannot be narrowed back to accredited only.');
        }

        DB::statement('ALTER TABLE certificates DROP CONSTRAINT certificates_kind_check');
        DB::statement("ALTER TABLE certificates ADD CONSTRAINT certificates_kind_check CHECK (kind IN ('accredited'))");
    }
};
