<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiValue;
use App\Support\ValueObjects\KpiNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

final class KpiValueHistory
{
    public function recordToday(Kpi $kpi, KpiNumber $value, User $actor): void
    {
        $today = CarbonImmutable::parse(Date::now(config()->string('app.display_timezone'))->toDateString());

        $entry = KpiValue::query()->firstOrNew(['kpi_id' => $kpi->id, 'measured_on' => $today->toDateString()]);
        $entry->fill(['value' => $value, 'recorded_by_user_id' => $actor->id])->save();
    }

    public function latestDay(Kpi $kpi): ?CarbonImmutable
    {
        $latest = KpiValue::query()->where('kpi_id', $kpi->id)->max('measured_on');

        return is_string($latest) ? CarbonImmutable::parse($latest) : null;
    }
}
