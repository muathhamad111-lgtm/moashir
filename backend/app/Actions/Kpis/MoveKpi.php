<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Kpis\ReorderDirection;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Services\Audit\AuditRecorder;
use App\Services\Platform\ListOrder;
use App\Services\Platform\TransactionLock;
use Illuminate\Support\Facades\DB;

final readonly class MoveKpi
{
    public function __construct(private TransactionLock $lock, private ListOrder $order, private AuditRecorder $audit) {}

    public function handle(User $actor, Kpi $kpi, ReorderDirection $direction): void
    {
        DB::transaction(function () use ($kpi, $direction): void {
            $this->lock->lock(Kpi::ORDER_LOCK);
            $kpis = Kpi::query()->ordered()->get();
            $before = $kpis->modelKeys();

            $this->order->shift($kpis, $kpi->id, $direction->offset());
            $after = Kpi::query()->ordered()->pluck('id')->all();

            if ($before !== $after) {
                $this->audit->record(new AuditEntry(AuditEvent::KpisReordered, $kpi, ['order' => $before], ['order' => $after, 'direction' => $direction->value]));
            }
        });
    }
}
