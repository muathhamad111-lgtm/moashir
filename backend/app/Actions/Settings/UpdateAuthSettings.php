<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\AuthSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Services\Settings\SettingsChangeReason;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAuthSettings
{
    private const string SECTION = 'auth';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private SettingsChangeReason $reason,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(User $actor, AuthSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        $this->reason->record($actor, $update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(self::SECTION);

            return $this->store->put(SettingKey::EntitySecondFactorEnabled, $update->entitySecondFactorEnabled, AuditEvent::AuthSettingsUpdated, $actor);
        });

        if (! $changed) {
            return;
        }

        $this->alerter->raiseToAdministrators(NotificationCode::EntitySecondFactorChanged);
        $this->reason->alertIfSuperAdmin($actor);
    }
}
