<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string CHECK = 'certificates_number_check';

    private const string PREVIOUS = "number ~ '^(TEST-)?CRT-[0-9]{4}-[0-9]{6}$' AND is_test = (number LIKE 'TEST-%')";

    private const string CURRENT = "number ~ '^(TEST-)?CRT-[0-9]{4}-[0-9]{6}$' AND (is_test OR number NOT LIKE 'TEST-%')";

    public function up(): void
    {
        $this->replace(self::CURRENT, '');
    }

    public function down(): void
    {
        $this->replace(self::PREVIOUS, ' NOT VALID');
    }

    private function replace(string $expression, string $validation): void
    {
        DB::statement(sprintf('ALTER TABLE certificates DROP CONSTRAINT IF EXISTS %s', self::CHECK));
        DB::statement(sprintf('ALTER TABLE certificates ADD CONSTRAINT %s CHECK (%s)%s', self::CHECK, $expression, $validation));
    }
};
