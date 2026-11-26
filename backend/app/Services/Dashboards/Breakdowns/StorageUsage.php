<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Models\Files\StoredFile;
use App\Models\Platform\StorageMeasurement;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\PeriodWindow;
use App\Support\ScalarValue;
use App\Support\ValueObjects\StorageQuota;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class StorageUsage implements OverviewBreakdownSource
{
    private const string FILE_BYTES = 'coalesce(sum(size_bytes) filter (where deleted_at is null), 0) as stored, '
        .'coalesce(sum(size_bytes) filter (where created_at >= ? and created_at < ?), 0) as added';

    public function __construct(private Config $config) {}

    public function keys(): array
    {
        return [OverviewBreakdownKey::Storage];
    }

    /**
     * @return array{storage: array{quota_bytes: int, used_bytes: int, added_in_period_bytes: int, warning_percent: int, critical_percent: int, measured_at: string|null}}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $quota = $this->quota();
        $files = AggregateRow::fetch(StoredFile::withTrashed(), self::FILE_BYTES, PeriodWindow::bindings($period));
        $latest = StorageMeasurement::query()->latest('measured_at')->first(['used_bytes', 'measured_at']);

        return [OverviewBreakdownKey::Storage->value => [
            'quota_bytes' => $quota->bytes(),
            'used_bytes' => max(ScalarValue::int($files['stored'] ?? null), $latest->used_bytes ?? 0),
            'added_in_period_bytes' => ScalarValue::int($files['added'] ?? null),
            'warning_percent' => $quota->warningPercent,
            'critical_percent' => $quota->criticalPercent,
            'measured_at' => $latest?->measured_at->toIso8601String(),
        ]];
    }

    private function quota(): StorageQuota
    {
        return new StorageQuota(
            $this->config->integer('moashir.storage.quota_gb'),
            $this->config->integer('moashir.storage.warning_percent'),
            $this->config->integer('moashir.storage.critical_percent'),
        );
    }
}
