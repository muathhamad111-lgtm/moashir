<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string PREVIOUS = "task IN ('orphan_file_purge', 'contact_message_purge', 'contact_message_erasure')";

    private const string CURRENT = "task IN ('orphan_file_purge', 'contact_message_purge', 'contact_message_erasure', 'demo_purge')";

    public function up(): void
    {
        $this->replace(self::CURRENT);
    }

    public function down(): void
    {
        DB::table('housekeeping_runs')->where('task', 'demo_purge')->delete();
        $this->replace(self::PREVIOUS);
    }

    private function replace(string $expression): void
    {
        DB::statement('ALTER TABLE housekeeping_runs DROP CONSTRAINT IF EXISTS housekeeping_runs_task_check');
        DB::statement(sprintf('ALTER TABLE housekeeping_runs ADD CONSTRAINT housekeeping_runs_task_check CHECK (%s)', $expression));
    }
};
