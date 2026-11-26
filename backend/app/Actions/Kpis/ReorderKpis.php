<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Services\Audit\AuditRecorder;
use App\Services\Platform\ListOrder;
use App\Services\Platform\TransactionLock;
use Illuminate\Support\Facades\DB;

final readonly class ReorderKpis
{
    public function __construct(private TransactionLock $lock, private ListOrder $order, private AuditRecorder $audit) {}

    /**
     * @param  list<int>  $orderedIds
     */
    public function handle(User $actor, array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            $this->lock->lock(Kpi::ORDER_LOCK);
            $kpis = Kpi::query()->ordered()->get();
            $before = $kpis->modelKeys();
            $this->order->apply($kpis, $orderedIds);

            $first = $kpis->first();

            if ($first instanceof Kpi && $before !== $orderedIds) {
                $this->audit->record(new AuditEntry(AuditEvent::KpisReordered, $first, ['order' => $before], ['order' => $orderedIds]));
            }
        });
    }
}
