<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'version' => 'version >= 1',
        'body' => "btrim(body) <> ''",
    ];

    public function up(): void
    {
        Schema::create('assessment_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('author_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('change_reason')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['assessment_id', 'version']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_summaries ADD CONSTRAINT assessment_summaries_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement('CREATE UNIQUE INDEX assessment_summaries_current ON assessment_summaries (assessment_id) WHERE superseded_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER assessment_summaries_versioned BEFORE UPDATE OR DELETE ON assessment_summaries
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_supersede_once();
            CREATE TRIGGER assessment_summaries_versioned_truncate BEFORE TRUNCATE ON assessment_summaries
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_summaries_versioned_truncate ON assessment_summaries');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_summaries_versioned ON assessment_summaries');
        Schema::dropIfExists('assessment_summaries');
    }
};
