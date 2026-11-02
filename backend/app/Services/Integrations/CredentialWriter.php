<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Audit\AuditEntry;
use App\Data\Integrations\CredentialChanges;
use App\Data\Integrations\CredentialWriteOutcome;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\User;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Audit\AuditRecorder;
use App\Support\Integrations\SecretStates;
use App\Support\ValueObjects\SecretValue;
use Illuminate\Support\Facades\Date;

final readonly class CredentialWriter
{
    public function __construct(private AuditRecorder $audit, private SecretStates $states) {}

    public function apply(IntegrationCredential $row, CredentialChanges $changes, ?User $actor): CredentialWriteOutcome
    {
        $beforeAudit = $this->auditView($row);
        $public = $this->mergedPublicConfig($row, $changes);
        [$secrets, $meta, $replaced] = $this->mergedSecrets($row, $changes, $actor);

        if ($row->exists && $public === $row->public_config && $replaced === []) {
            return CredentialWriteOutcome::unchanged();
        }

        if (! $row->exists && $public === [] && $secrets === []) {
            return CredentialWriteOutcome::unchanged();
        }

        $resetsTest = ! $row->exists || $public !== $row->public_config || $this->touchesTestedFields($row, $replaced);

        $row->forceFill([
            'public_config' => $public,
            'secrets' => $secrets === [] ? null : $secrets,
            'secret_meta' => $meta,
            'updated_by_user_id' => $actor?->id,
            'config_version' => $row->exists ? $row->config_version + 1 : 1,
        ]);

        if ($resetsTest) {
            $this->resetLastTest($row);
        }

        $row->save();

        $this->audit->record(new AuditEntry($row->kind->updatedAuditEvent(), $row, $beforeAudit, $this->auditView($row)));
        $this->auditReplacedSecrets($row, $changes, $replaced);

        return new CredentialWriteOutcome(true, $replaced);
    }

    /**
     * @param  list<string>  $replaced
     */
    private function touchesTestedFields(IntegrationCredential $row, array $replaced): bool
    {
        return array_any($replaced, static fn (string $field): bool => $row->provider->affectsConnectionTest($field));
    }

    private function resetLastTest(IntegrationCredential $row): void
    {
        $row->forceFill([
            'last_test_at' => null,
            'last_test_ok' => null,
            'last_test_error_kind' => null,
            'last_test_latency_ms' => null,
            'last_test_by_user_id' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedPublicConfig(IntegrationCredential $row, CredentialChanges $changes): array
    {
        $public = $row->exists ? $row->public_config : [];

        foreach ($changes->publicConfig as $field => $value) {
            if ($value === null || $value === '') {
                unset($public[$field]);

                continue;
            }

            $public[$field] = $value;
        }

        ksort($public);

        return $public;
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, array<string, mixed>>, 2: list<string>}
     */
    private function mergedSecrets(IntegrationCredential $row, CredentialChanges $changes, ?User $actor): array
    {
        $secrets = $row->exists ? ($row->secrets ?? []) : [];
        $meta = $row->exists ? $row->secret_meta : [];
        $replaced = [];

        foreach ($changes->secrets as $field => $value) {
            if (! $value instanceof SecretValue) {
                if (array_key_exists($field, $secrets)) {
                    unset($secrets[$field], $meta[$field]);
                    $replaced[] = $field;
                }

                continue;
            }

            if (isset($secrets[$field]) && hash_equals($secrets[$field], $value->reveal())) {
                continue;
            }

            $secrets[$field] = $value->reveal();
            $meta[$field] = $this->states->metaFor($row->provider, $field, $value, $actor, Date::now());
            $replaced[] = $field;
        }

        return [$secrets, $meta, $replaced];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditView(IntegrationCredential $row): array
    {
        if (! $row->exists) {
            return [];
        }

        return [
            'provider' => $row->provider->value,
            'mode' => $row->mode->value,
            'public_config' => $row->public_config,
            'secrets' => $this->states->auditSummary($row->secret_meta),
            'is_active' => $row->is_active,
        ];
    }

    /**
     * @param  list<string>  $replaced
     */
    private function auditReplacedSecrets(IntegrationCredential $row, CredentialChanges $changes, array $replaced): void
    {
        foreach ($replaced as $field) {
            $action = ($changes->secrets[$field] ?? null) instanceof SecretValue ? 'replaced' : 'cleared';

            $this->audit->record(new AuditEntry(AuditEvent::SecretReplaced, $row, [], [
                'kind' => $row->kind->value,
                'provider' => $row->provider->value,
                'mode' => $row->mode->value,
                'field' => $field,
                'action' => $action,
            ]));
        }
    }
}
