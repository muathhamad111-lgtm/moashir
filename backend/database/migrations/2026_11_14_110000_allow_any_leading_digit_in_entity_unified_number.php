<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string CONSTRAINT = 'entities_unified_number_check';

    public function up(): void
    {
        $this->replaceCheck('^[0-9]{10}$');
    }

    /**
     * Restoring the earlier «starts with 7» rule fails while any stored number starts with another digit.
     */
    public function down(): void
    {
        $this->replaceCheck('^7[0-9]{9}$');
    }

    private function replaceCheck(string $pattern): void
    {
        DB::statement(sprintf('ALTER TABLE entities DROP CONSTRAINT IF EXISTS %s', self::CONSTRAINT));
        DB::statement(sprintf("ALTER TABLE entities ADD CONSTRAINT %s CHECK (unified_number IS NULL OR unified_number ~ '%s')", self::CONSTRAINT, $pattern));
    }
};
