<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Data\Kpis\KpiMeasurement;
use App\Enums\Audit\AuditEvent;
use App\Events\Kpis\KpiValueChanged;
use App\Exceptions\Kpis\KpiRejected;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiValue;
use App\Services\Audit\AuditRecorder;
use App\Services\Kpis\KpiValueHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RecordKpiMeasurement
{
    public function __construct(private KpiValueHistory $history, private AuditRecorder $audit) {}

    public function handle(User $actor, Kpi $kpi, KpiMeasurement $measurement): KpiValue
    {
        return DB::transaction(function () use ($actor, $kpi, $measurement): KpiValue {
            $current = Kpi::query()->lockForUpdate()->findOrFail($kpi->id);
            $day = $measurement->measuredOn->toDateString();

            if (KpiValue::query()->where('kpi_id', $current->id)->whereDate('measured_on', $day)->exists()) {
                throw KpiRejected::measurementExists();
            }

            $latest = $this->history->latestDay($current);
            $entry = KpiValue::query()->create([
                'kpi_id' => $current->id,
                'value' => $measurement->value,
                'measured_on' => $day,
                'recorded_by_user_id' => $actor->id,
            ]);

            $this->audit->record(new AuditEntry(AuditEvent::KpiValueRecorded, $current, [], ['value' => $measurement->value->value, 'measured_on' => $day]));

            if ($this->isLatest($measurement->measuredOn, $latest)) {
                $this->applyAsCurrent($current, $measurement, $actor);
            }

            return $entry;
        });
    }

    private function isLatest(CarbonImmutable $day, ?CarbonImmutable $latest): bool
    {
        return ! $latest instanceof CarbonImmutable || $day->greaterThan($latest);
    }

    private function applyAsCurrent(Kpi $kpi, KpiMeasurement $measurement, User $actor): void
    {
        if ($kpi->value?->value === $measurement->value->value) {
            return;
        }

        $kpi->forceFill(['value' => $measurement->value, 'value_updated_at' => Date::now(), 'updated_by_user_id' => $actor->id])->save();
        KpiValueChanged::dispatch($kpi, $actor->id);
    }
}
