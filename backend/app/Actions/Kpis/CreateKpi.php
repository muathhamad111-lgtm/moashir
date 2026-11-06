<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Data\Kpis\KpiChanges;
use App\Enums\Audit\AuditEvent;
use App\Events\Kpis\KpiCreated;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Services\Audit\AuditRecorder;
use App\Services\Kpis\KpiAuditSnapshot;
use App\Services\Kpis\KpiSlugs;
use App\Services\Kpis\KpiValueHistory;
use App\Services\Kpis\KpiWriter;
use App\Services\Platform\ListOrder;
use App\Services\Platform\TransactionLock;
use App\Support\ValueObjects\KpiNumber;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Support\Facades\DB;

final readonly class CreateKpi
{
    public function __construct(
        private TransactionLock $lock,
        private KpiWriter $writer,
        private KpiSlugs $slugs,
        private ListOrder $order,
        private KpiValueHistory $history,
        private KpiAuditSnapshot $snapshot,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, KpiChanges $changes): Kpi
    {
        return DB::transaction(function () use ($actor, $changes): Kpi {
            $this->lock->lock(Kpi::ORDER_LOCK);

            $kpi = new Kpi;
            $name = $changes->attributes['name'] ?? null;
            $kpi->forceFill([
                'slug' => $changes->slug() ?? ($name instanceof LocalizedContent ? $this->slugs->generateFor($name) : 'kpi'),
                'sort_order' => $this->order->nextPosition(Kpi::query()->max('sort_order')),
            ]);
            $this->writer->write($kpi, $changes, $actor);

            if ($kpi->value instanceof KpiNumber) {
                $this->history->recordToday($kpi, $kpi->value, $actor);
            }

            $this->audit->record(new AuditEntry(AuditEvent::KpiCreated, $kpi, [], $this->snapshot->of($kpi)));
            KpiCreated::dispatch($kpi, $actor->id);

            return $kpi;
        });
    }
}
