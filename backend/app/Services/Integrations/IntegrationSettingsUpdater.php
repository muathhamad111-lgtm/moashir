<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Integrations\ActivationRequest;
use App\Data\Integrations\IntegrationUpdate;
use App\Data\Integrations\ProviderCredentialChanges;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Models\Identity\User;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Notifications\SecurityAlerter;
use App\Services\Settings\SettingsStore;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class IntegrationSettingsUpdater
{
    public function __construct(
        private IntegrationRows $rows,
        private CredentialWriter $writer,
        private SettingsStore $store,
        private IntegrationModeSwitcher $modes,
        private IntegrationActivation $activation,
        private IntegrationConfigVersions $versions,
        private SecurityAlerter $alerter,
        private RequestContext $context,
    ) {}

    public function begin(IntegrationKind $kind, IntegrationUpdate $update, User $actor): void
    {
        $this->rows->lockKind($kind);

        if ($update->touchesSecrets() && $actor->isSuperAdmin()) {
            if ($update->reason === null || trim($update->reason) === '') {
                throw IntegrationRejected::reasonRequired();
            }

            $this->context->recordReason($update->reason);
        }

        DB::afterCommit(fn () => $this->versions->forget($kind));
    }

    /**
     * @return list<string>
     */
    public function writeCredentials(IntegrationUpdate $update, User $actor): array
    {
        $replaced = [];

        foreach ($update->credentials as $credential) {
            if ($credential->changes->isEmpty()) {
                continue;
            }

            $outcome = $this->writer->apply($this->rowFor($credential), $credential->changes, $actor);
            $replaced = [...$replaced, ...array_map(static fn (string $field): string => $credential->provider->value.'.'.$field, $outcome->replacedSecrets)];
        }

        if ($replaced !== []) {
            DB::afterCommit(fn () => $this->alerter->raise(NotificationCode::IntegrationSecretReplaced));
        }

        return $replaced;
    }

    public function writeSettings(IntegrationKind $kind, IntegrationUpdate $update, User $actor): void
    {
        foreach ($update->settings as $key => $value) {
            $this->store->put(SettingKey::from($key), $value, $kind->updatedAuditEvent(), $actor);
        }
    }

    public function applyMode(IntegrationKind $kind, IntegrationUpdate $update, User $actor): bool
    {
        if (! $update->mode instanceof IntegrationMode) {
            return false;
        }

        return $this->modes->switch($kind, $update->mode, $actor, $update->reason);
    }

    public function applyActivation(IntegrationKind $kind, IntegrationUpdate $update, string $field): bool
    {
        $request = $update->activation;

        if (! $request instanceof ActivationRequest) {
            return false;
        }

        if ($request->deactivates()) {
            return $this->activation->deactivate($kind);
        }

        $provider = $request->provider ?? throw IntegrationRejected::configurationIncomplete($field);

        return $this->activation->activate($this->rows->findOrNew($provider), $field);
    }

    private function rowFor(ProviderCredentialChanges $credential): IntegrationCredential
    {
        return $this->rows->findOrNew($credential->provider, $credential->mode);
    }
}
