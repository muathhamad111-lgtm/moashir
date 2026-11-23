<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Several award cycles may run at the same time (operator, 2026-11-22: «الغ قيد عدم وجود دورتين بنفس الوقت دائما،
 * واربطه باعداد ضمن الاعدادات العامة لتحديد السماح باضافة عدة دورات بنفس الفترة او دورة جديدة مع وجود دورة غير مغلقة
 * او التقيد بدورة واحدة قائمة فقط»؛ default and change: «دورة واحدة، والتغيير للمستقبل»؛ narrowing the setting while
 * cycles run: «يُقبل ويسري للمستقبل»). The rule moves from the database to the `assessment.advanced_cycle_concurrency`
 * setting, which defaults to one running cycle. A change is always accepted and never touches running cycles: the mode
 * in force is checked only when a cycle opens or reopens (manually or on schedule), under a transaction lock. The
 * rollback restores the index only while at most one cycle runs; otherwise it stops with a clear message instead of
 * closing cycles.
 */
return new class extends Migration
{
    private const string INDEX = "CREATE UNIQUE INDEX award_cycles_one_running ON award_cycles ((true)) WHERE status NOT IN ('draft', 'closed')";

    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS award_cycles_one_running');
    }

    public function down(): void
    {
        if (DB::table('award_cycles')->whereNotIn('status', ['draft', 'closed'])->count() > 1) {
            throw new RuntimeException('More than one award cycle is running; close the extra cycles before restoring the one-running-cycle index.');
        }

        DB::statement(self::INDEX);
    }
};
