<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string OPEN_STATES = "status IN ('created', 'pending', 'under_review')";

    private const string PREVIOUS_PURPOSE = "purpose IN ('accredited', 'bag')";

    private const string CURRENT_PURPOSE = "purpose IN ('self', 'accredited', 'award', 'bag')";

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('award_cycle_id')->nullable()->constrained('award_cycles')->restrictOnDelete();
        });

        $this->replacePurposeCheck(self::CURRENT_PURPOSE);
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_award_cycle_check CHECK ((purpose = 'award') = (award_cycle_id IS NOT NULL))");
        DB::statement('DROP INDEX IF EXISTS payments_one_open_order');
        DB::statement(sprintf("CREATE UNIQUE INDEX payments_one_open_order ON payments (entity_id, purpose) WHERE %s AND purpose <> 'award'", self::OPEN_STATES));
        DB::statement(sprintf("CREATE UNIQUE INDEX payments_one_open_award_order ON payments (entity_id, award_cycle_id) WHERE %s AND purpose = 'award'", self::OPEN_STATES));
    }

    public function down(): void
    {
        if (DB::table('payments')->whereIn('purpose', ['self', 'award'])->exists()) {
            throw new RuntimeException('Self or award payments exist; this migration cannot be rolled back without losing payment records.');
        }

        DB::statement('DROP INDEX IF EXISTS payments_one_open_award_order');
        DB::statement('DROP INDEX IF EXISTS payments_one_open_order');
        DB::statement(sprintf('CREATE UNIQUE INDEX payments_one_open_order ON payments (entity_id, purpose) WHERE %s', self::OPEN_STATES));
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_award_cycle_check');
        $this->replacePurposeCheck(self::PREVIOUS_PURPOSE);

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('award_cycle_id');
        });
    }

    private function replacePurposeCheck(string $expression): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_purpose_check');
        DB::statement(sprintf('ALTER TABLE payments ADD CONSTRAINT payments_purpose_check CHECK (%s)', $expression));
    }
};
