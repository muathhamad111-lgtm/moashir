<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Actions\Identity\ApplyTwoFactorPolicy;
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
        private ApplyTwoFactorPolicy $applyTwoFactor,
    ) {}

    public function handle(User $actor, AuthSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        $this->reason->record($actor, $update->reason);

        [$channelsChanged, $otherChanged, $twoFactorChanged] = DB::transaction(function () use ($actor, $update): array {
            $this->lock->lock(self::SECTION);
            $twoFactorChanged = $this->putTwoFactor($actor, $update);

            if ($twoFactorChanged) {
                $this->applyTwoFactor->handle();
            }

            $emailChanged = $this->putGeneralEmail($actor, $update);
            $reviewChanged = $this->putRegistrationReview($actor, $update);

            return [$this->putChannels($actor, $update), $emailChanged || $reviewChanged, $twoFactorChanged];
        });

        $this->raiseAlerts($channelsChanged, $twoFactorChanged);

        if ($channelsChanged || $otherChanged || $twoFactorChanged) {
            $this->reason->alertIfSuperAdmin($actor);
        }
    }

    private function raiseAlerts(bool $channelsChanged, bool $twoFactorChanged): void
    {
        if ($channelsChanged) {
            $this->alerter->raiseToAdministrators(NotificationCode::EntitySignInCodeChannelsChanged);
        }

        if ($twoFactorChanged) {
            $this->alerter->raiseToAdministrators(NotificationCode::TwoFactorPolicyChanged);
        }
    }

    private function putTwoFactor(User $actor, AuthSettingsUpdate $update): bool
    {
        $enabled = $update->twoFactorEnabled !== null
            && $this->store->put(SettingKey::TwoFactorEnabled, $update->twoFactorEnabled, AuditEvent::AuthSettingsUpdated, $actor);
        $roles = $update->twoFactorRoles !== null
            && $this->store->put(SettingKey::TwoFactorRoles, $update->twoFactorRoleValues(), AuditEvent::AuthSettingsUpdated, $actor);

        return $enabled || $roles;
    }

    private function putChannels(User $actor, AuthSettingsUpdate $update): bool
    {
        return $update->entitySignInCodeChannels !== null
            && $this->store->put(SettingKey::EntitySignInCodeChannels, $update->channelValues(), AuditEvent::AuthSettingsUpdated, $actor);
    }

    private function putGeneralEmail(User $actor, AuthSettingsUpdate $update): bool
    {
        return $update->generalEmailNotifications !== null
            && $this->store->put(SettingKey::GeneralEmailNotifications, $update->generalEmailNotifications, AuditEvent::AuthSettingsUpdated, $actor);
    }

    private function putRegistrationReview(User $actor, AuthSettingsUpdate $update): bool
    {
        return $update->entityRegistrationReview !== null
            && $this->store->put(SettingKey::EntityRegistrationReview, $update->entityRegistrationReview, AuditEvent::AuthSettingsUpdated, $actor);
    }
}
