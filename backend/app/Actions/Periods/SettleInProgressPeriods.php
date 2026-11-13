<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Enums\Periods\EntityPeriodState;
use App\Models\Assessments\EntityPeriodStatus;
use App\Models\Entities\Entity;
use App\Services\Periods\PeriodicSettingsReader;
use App\Services\Periods\PeriodStatusRecorder;
use Illuminate\Database\Eloquent\Collection;

final readonly class SettleInProgressPeriods
{
    private const int CHUNK = 500;

    public function __construct(private PeriodicSettingsReader $settings, private PeriodStatusRecorder $recorder) {}

    public function handle(): int
    {
        $largestReminder = $this->settings->read()->largestReminderDays();
        $settled = 0;

        EntityPeriodStatus::query()
            ->where('status', EntityPeriodState::InProgress)
            ->with(['period', 'entity'])
            ->chunkById(self::CHUNK, function (Collection $rows) use ($largestReminder, &$settled): void {
                foreach ($rows->groupBy('assessment_period_id') as $byPeriod) {
                    $first = $byPeriod->first();

                    if ($first instanceof EntityPeriodStatus) {
                        $entities = new Collection($byPeriod->map(static fn (EntityPeriodStatus $row): Entity => $row->entity)->values()->all());
                        $this->recorder->record($first->period, $entities, $largestReminder);
                        $settled += $byPeriod->count();
                    }
                }
            });

        return $settled;
    }
}
