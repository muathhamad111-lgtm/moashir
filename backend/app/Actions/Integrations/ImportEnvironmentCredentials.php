<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Audit\AuditEntry;
use App\Data\Integrations\CredentialChanges;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Console\IntegrationCommandRejected;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Audit\AuditRecorder;
use App\Services\Integrations\CredentialWriter;
use App\Services\Integrations\EnvironmentCredentials;
use App\Services\Integrations\IntegrationConfigVersions;
use App\Services\Integrations\IntegrationRows;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

final readonly class ImportEnvironmentCredentials
{
    public function __construct(
        private EnvironmentCredentials $environment,
        private IntegrationRows $rows,
        private CredentialWriter $writer,
        private SettingsStore $settings,
        private AuditRecorder $audit,
        private IntegrationConfigVersions $versions,
    ) {}

    public function handle(IntegrationKind $kind): IntegrationCredential
    {
        $credentials = $this->environment->read($kind) ?? throw IntegrationCommandRejected::nothingToImport($kind->value);

        $row = DB::transaction(function () use ($kind, $credentials): IntegrationCredential {
            if ($this->rows->lockKind($kind)->isNotEmpty()) {
                throw IntegrationCommandRejected::alreadyConfigured($kind->value);
            }

            if ($kind->hasModePerCredential()) {
                $this->settings->put(SettingKey::modeOf($kind), $credentials->mode->value, AuditEvent::IntegrationEnvImported, null);
            }

            $row = $this->rows->findOrNew($credentials->provider(), $credentials->mode);
            $this->writer->apply($row, $this->changesOf($credentials), null);
            $row->forceFill(['is_active' => true])->save();

            $this->audit->record(new AuditEntry(AuditEvent::IntegrationEnvImported, $row, [], [
                'provider' => $row->provider->value,
                'mode' => $row->mode->value,
                'public_fields' => array_keys($credentials->publicConfig),
                'secret_fields' => $credentials->secretFieldNames(),
            ]));

            return $row;
        });

        $this->versions->forget($kind);

        return $row;
    }

    private function changesOf(ResolvedCredentials $credentials): CredentialChanges
    {
        $secrets = [];

        foreach ($credentials->secretFieldNames() as $field) {
            $secrets[$field] = $credentials->secret($field);
        }

        return new CredentialChanges($credentials->publicConfig, $secrets);
    }
}
