<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string CHECK = 'demo_records_kind_check';

    private const string INSIGHTS_ASSESSMENT = 'insights_assessment';

    public function up(): void
    {
        $this->replaceKindCheck("'entity', 'user', 'insights_assessment'");
    }

    public function down(): void
    {
        DB::table('demo_records')->where('kind', self::INSIGHTS_ASSESSMENT)->delete();
        $this->replaceKindCheck("'entity', 'user'");
    }

    private function replaceKindCheck(string $kinds): void
    {
        DB::statement(sprintf('ALTER TABLE demo_records DROP CONSTRAINT IF EXISTS %s', self::CHECK));
        DB::statement(sprintf('ALTER TABLE demo_records ADD CONSTRAINT %s CHECK (kind IN (%s))', self::CHECK, $kinds));
    }
};
