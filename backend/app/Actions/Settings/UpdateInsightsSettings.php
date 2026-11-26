<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\InsightsSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Insights\InsightsRulesReader;
use App\Services\Settings\SettingsChangeReason;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;
use Illuminate\Support\Facades\DB;

final readonly class UpdateInsightsSettings
{
    private const string SECTION = 'insights';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private SettingsChangeReason $reasons,
        private InsightsRulesReader $rules,
    ) {}

    public function handle(User $actor, InsightsSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        $this->reasons->record($actor, $update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(self::SECTION);
            $current = $this->rules->read();
            $changed = false;

            if ($update->gaps instanceof GapThresholds && ! $current->gaps->hasSameBoundsAs($update->gaps)) {
                $changed = $this->store->put(SettingKey::InsightsGapThresholds, $current->gaps->revisedTo($update->gaps)->toArray(), AuditEvent::InsightsSettingsUpdated, $actor);
            }

            if ($update->bands instanceof MaturityBounds && ! $current->bands->hasSameBoundsAs($update->bands)) {
                $changed = $this->store->put(SettingKey::InsightsMaturityBands, $current->bands->revisedTo($update->bands)->toArray(), AuditEvent::InsightsSettingsUpdated, $actor) || $changed;
            }

            return $changed;
        });

        if ($changed) {
            $this->reasons->alertIfSuperAdmin($actor);
        }
    }
}
