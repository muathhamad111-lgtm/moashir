<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Integrations\IntegrationSnapshot;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\CredentialSource;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Settings\SettingKey;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use Illuminate\Contracts\Foundation\Application;

final readonly class IntegrationSnapshotReader
{
    private const array ACTOR_KEYS = ['updated_by_user_id', 'confirmed_by_user_id', 'data_transfer_acknowledged_by'];

    public function __construct(
        private SettingsStore $settings,
        private EnvironmentCredentials $environment,
        private IntegrationActorNames $names,
        private Application $app,
    ) {}

    public function read(IntegrationKind $kind): IntegrationSnapshot
    {
        $rows = array_values(IntegrationCredential::query()->ofKind($kind)->orderBy('id')->get()->all());
        $environment = $rows === [] ? $this->environment->for($kind) : null;
        $settings = $this->settings->many($this->settingKeysOf($kind));
        $source = match (true) {
            $rows !== [] => CredentialSource::Settings,
            $environment instanceof ResolvedCredentials => CredentialSource::Environment,
            default => CredentialSource::None,
        };

        return new IntegrationSnapshot($kind, $source, $rows, $environment, $settings, $this->names->for($this->actorIds($rows, $settings)), $this->app->isProduction());
    }

    /**
     * @return list<SettingKey>
     */
    private function settingKeysOf(IntegrationKind $kind): array
    {
        return array_values(array_filter(SettingKey::cases(), static fn (SettingKey $key): bool => $key->group()->value === $kind->value));
    }

    /**
     * @param  list<IntegrationCredential>  $rows
     * @param  array<string, mixed>  $settings
     * @return list<int>
     */
    private function actorIds(array $rows, array $settings): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = (int) $row->updated_by_user_id;
            $ids[] = (int) $row->last_test_by_user_id;
            $ids = [...$ids, ...$this->idsIn($row->secret_meta), ...$this->idsIn([$row->provider_refs])];
        }

        return [...$ids, ...$this->idsIn($settings)];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<int>
     */
    private function idsIn(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            foreach (self::ACTOR_KEYS as $key) {
                $ids[] = ScalarValue::int($value[$key] ?? null);
            }
        }

        return $ids;
    }
}
