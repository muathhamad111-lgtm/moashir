<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Events\Kpis\KpiUnpublished;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Services\Audit\AuditRecorder;
use App\Services\Kpis\KpiAuditSnapshot;
use App\Services\Platform\ListOrder;
use App\Services\Platform\TransactionLock;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveKpi
{
    public function __construct(
        private TransactionLock $lock,
        private ListOrder $order,
        private KpiAuditSnapshot $snapshot,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Kpi $kpi): void
    {
        DB::transaction(function () use ($actor, $kpi): void {
            $this->lock->lock(Kpi::ORDER_LOCK);
            $current = Kpi::query()->lockForUpdate()->findOrFail($kpi->id);
            $before = $this->snapshot->of($current);
            $wasPublished = $current->is_published;

            $current->forceFill(['is_published' => false, 'show_on_homepage' => false, 'updated_by_user_id' => $actor->id])->save();
            $current->delete();
            $this->order->resequence(Kpi::query()->ordered()->get());
            $this->audit->record(new AuditEntry(AuditEvent::KpiArchived, $current, $before, ['archived' => true]));

            if ($wasPublished) {
                KpiUnpublished::dispatch($current, $actor->id);
            }
        });
    }
}
