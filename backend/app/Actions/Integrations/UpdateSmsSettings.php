<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Integrations\IntegrationUpdate;
use App\Data\Integrations\ProviderCredentialChanges;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationRows;
use App\Services\Integrations\IntegrationSettingsUpdater;
use App\Services\Integrations\MsegatTemplateGuard;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSmsSettings
{
    public function __construct(
        private IntegrationSettingsUpdater $updater,
        private IntegrationRows $rows,
        private MsegatTemplateGuard $templates,
        private SettingsStore $store,
    ) {}

    public function handle(User $actor, IntegrationUpdate $update): void
    {
        DB::transaction(function () use ($actor, $update): void {
            $this->updater->begin(IntegrationKind::Sms, $update, $actor);
            $this->updater->writeCredentials($update, $actor);
            $this->updater->writeSettings(IntegrationKind::Sms, $this->withMergedTemplates($update), $actor);

            if ($this->needsTemplateCheck($update)) {
                $this->templates->assertApproved($this->rows->find(IntegrationProvider::Msegat));
            }

            $this->updater->applyMode(IntegrationKind::Sms, $update, $actor);
            $this->updater->applyActivation(IntegrationKind::Sms, $update, 'active_provider');
        });
    }

    private function withMergedTemplates(IntegrationUpdate $update): IntegrationUpdate
    {
        if (! $update->hasSetting(SettingKey::SmsTemplates)) {
            return $update;
        }

        $stored = $this->store->get(SettingKey::SmsTemplates);
        $provided = $update->setting(SettingKey::SmsTemplates);
        $merged = [...(is_array($stored) ? $stored : []), ...(is_array($provided) ? $provided : [])];
        ksort($merged);

        return $update->withSetting(SettingKey::SmsTemplates, $merged);
    }

    private function needsTemplateCheck(IntegrationUpdate $update): bool
    {
        $restrictionChanged = array_any(
            $update->credentials,
            static fn (ProviderCredentialChanges $credential): bool => $credential->provider === IntegrationProvider::Msegat
                && array_key_exists('template_restricted', $credential->changes->publicConfig),
        );

        return $update->hasSetting(SettingKey::SmsTemplates)
            || $restrictionChanged
            || $update->activation?->provider === IntegrationProvider::Msegat;
    }
}
