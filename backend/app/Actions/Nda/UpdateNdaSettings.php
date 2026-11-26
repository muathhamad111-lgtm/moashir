<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Data\Nda\NdaSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Settings\SettingsChangeReason;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

/**
 * Changes the roles that must accept the agreement and the reminder delay, with a reason, through the audited
 * settings store. Taking a role out of scope keeps every acceptance already recorded; adding one gates its
 * holders at their next request.
 */
final readonly class UpdateNdaSettings
{
    private const string SECTION = 'nda';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private SettingsChangeReason $reason,
    ) {}

    public function handle(User $actor, NdaSettingsUpdate $update): void
    {
        $this->reason->record($actor, $update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(self::SECTION);
            $roles = $update->roles !== null
                && $this->store->put(SettingKey::NdaRoles, $update->roleValues(), AuditEvent::NdaScopeChanged, $actor);
            $delay = $update->reminderDays !== null
                && $this->store->put(SettingKey::NdaReminderDays, $update->reminderDays, AuditEvent::NdaReminderDelayChanged, $actor);

            return $roles || $delay;
        });

        if ($changed) {
            $this->reason->alertIfSuperAdmin($actor);
        }
    }
}
