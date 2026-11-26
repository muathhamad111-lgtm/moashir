<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Periods\PeriodicSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Periods\PeriodicSettingsReader;
use App\Services\Periods\PeriodSchedule;
use App\Services\Settings\SettingsChangeReason;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePeriodicSettings
{
    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private SettingsChangeReason $reasons,
        private PeriodicSettingsReader $settings,
        private PeriodSchedule $schedule,
    ) {}

    public function handle(User $actor, PeriodicSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        $this->reasons->record($actor, $update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(PeriodSchedule::LOCK);
            $changed = false;

            foreach (PeriodicSettingsUpdate::storedValuesOf($update->appliedTo($this->settings->read())) as $key => $value) {
                $changed = $this->store->put(SettingKey::from($key), $value, AuditEvent::PeriodicSettingsUpdated, $actor) || $changed;
            }

            if ($changed) {
                $this->schedule->sync();
            }

            return $changed;
        });

        if ($changed) {
            $this->reasons->alertIfSuperAdmin($actor);
        }
    }
}
