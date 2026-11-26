<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence of a main criterion (criteria batch S7): a file belongs to a main criterion (`main_code`) or to a
 * sub-criterion (`sub_code`), never both. Indicator attachments stay stored under their sub with the indicator
 * requirement's key, as before. Codes are stable across versions, so evidence still follows its code on a rebind.
 */
return new class extends Migration
{
    private const string OWNER_CHECK = 'evidence_files_owner_check';

    public function up(): void
    {
        Schema::table('evidence_files', function (Blueprint $table): void {
            $table->string('main_code', 20)->nullable()->after('assessment_id');
            $table->string('sub_code', 20)->nullable()->change();
            $table->index(['assessment_id', 'main_code', 'status']);
        });

        DB::statement(sprintf('ALTER TABLE evidence_files ADD CONSTRAINT %s CHECK (num_nonnulls(main_code, sub_code) = 1)', self::OWNER_CHECK));
    }

    /**
     * Rolling back removes the evidence rows of main criteria: the old schema cannot hold them. Their stored files stay
     * in place and can be detached by the file cleanup.
     */
    public function down(): void
    {
        DB::statement(sprintf('ALTER TABLE evidence_files DROP CONSTRAINT IF EXISTS %s', self::OWNER_CHECK));
        DB::table('evidence_files')->whereNotNull('main_code')->delete();

        Schema::table('evidence_files', function (Blueprint $table): void {
            $table->dropIndex(['assessment_id', 'main_code', 'status']);
            $table->dropColumn('main_code');
            $table->string('sub_code', 20)->nullable(false)->change();
        });
    }
};
