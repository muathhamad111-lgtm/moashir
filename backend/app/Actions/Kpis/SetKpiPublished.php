<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Events\Kpis\KpiPublished;
use App\Events\Kpis\KpiUnpublished;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SetKpiPublished
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $actor, Kpi $kpi, bool $published): void
    {
        DB::transaction(function () use ($actor, $kpi, $published): void {
            $current = Kpi::query()->lockForUpdate()->findOrFail($kpi->id);

            if ($current->is_published === $published) {
                return;
            }

            $current->forceFill([
                'is_published' => $published,
                'published_at' => $published ? Date::now() : $current->published_at,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record(new AuditEntry(
                $published ? AuditEvent::KpiPublished : AuditEvent::KpiUnpublished,
                $current,
                ['published' => ! $published],
                ['published' => $published],
            ));

            $published ? KpiPublished::dispatch($current, $actor->id) : KpiUnpublished::dispatch($current, $actor->id);
        });
    }
}
