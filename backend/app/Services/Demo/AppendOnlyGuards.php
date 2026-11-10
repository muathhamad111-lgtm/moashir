<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AppendOnlyGuards
{
    private const array TABLES = [
        'assessment_assignments',
        'assessment_criteria_moves',
        'assessment_feedback_items',
        'assessment_result_snapshots',
        'assessment_scores',
        'assessment_status_transitions',
        'assessment_summaries',
        'authentication_events',
        'bank_transfers',
        'certificates',
        'committee_averages',
        'committee_discussion_entries',
        'committee_notes',
        'committee_scores',
        'entity_profile_changes',
        'filtering_decisions',
        'payment_events',
        'payment_receipts',
        'refunds',
        'secretary_decisions',
        'terms_acceptances',
    ];

    public function areOwnedByCurrentRole(): bool
    {
        $placeholders = implode(', ', array_fill(0, count(self::TABLES), '?'));
        $row = DB::selectOne(
            "SELECT count(*) AS foreign_tables FROM pg_tables WHERE schemaname = current_schema() AND tablename IN ({$placeholders}) AND NOT pg_has_role(current_user, tableowner, 'USAGE')",
            self::TABLES,
        );

        return is_object($row) && ScalarValue::int($row->foreign_tables ?? null, -1) === 0;
    }

    public function suspendForCurrentTransaction(): void
    {
        $this->alterAll('DISABLE');
    }

    public function restore(): void
    {
        $this->alterAll('ENABLE');
    }

    private function alterAll(string $action): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The append-only guards are suspended only inside the transaction of one purge batch.');
        }

        foreach (self::TABLES as $table) {
            DB::statement(sprintf('ALTER TABLE %s %s TRIGGER USER', $table, $action));
        }
    }
}
