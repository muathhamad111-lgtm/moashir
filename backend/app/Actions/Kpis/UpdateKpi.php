<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Data\Kpis\KpiChanges;
use App\Enums\Audit\AuditEvent;
use App\Events\Kpis\KpiValueChanged;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Services\Audit\AuditRecorder;
use App\Services\Kpis\KpiAuditSnapshot;
use App\Services\Kpis\KpiValueHistory;
use App\Services\Kpis\KpiWriter;
use App\Services\Platform\TransactionLock;
use App\Support\ValueObjects\KpiNumber;
use Illuminate\Support\Facades\DB;

final readonly class UpdateKpi
{
    public function __construct(
        private TransactionLock $lock,
        private KpiWriter $writer,
        private KpiValueHistory $history,
        private KpiAuditSnapshot $snapshot,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Kpi $kpi, KpiChanges $changes): void
    {
        if ($changes->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($actor, $kpi, $changes): void {
            $this->lock->lock(Kpi::ORDER_LOCK);
            $current = Kpi::query()->lockForUpdate()->findOrFail($kpi->id);
            $before = $this->snapshot->of($current);
            $this->writer->write($current, $changes, $actor);
            $after = $this->snapshot->of($current);

            if ($before === $after) {
                return;
            }

            $this->audit->record(new AuditEntry(AuditEvent::KpiUpdated, $current, $before, $after));

            if ($before['value'] !== $after['value'] && $current->value instanceof KpiNumber) {
                $this->history->recordToday($current, $current->value, $actor);
                KpiValueChanged::dispatch($current, $actor->id);
            }
        });
    }
}
