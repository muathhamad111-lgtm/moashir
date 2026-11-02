<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Data\Integrations\IntegrationSnapshot;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Models\Integrations\IntegrationCredential;
use App\Support\ScalarValue;

final readonly class CredentialPresenter
{
    private SecretStates $states;

    public function __construct(private IntegrationSnapshot $snapshot)
    {
        $this->states = new SecretStates;
    }

    public function snapshot(): IntegrationSnapshot
    {
        return $this->snapshot;
    }

    public function isConfigured(IntegrationProvider $provider, ?IntegrationMode $mode = null): bool
    {
        $row = $this->snapshot->row($provider, $mode);

        if ($row instanceof IntegrationCredential) {
            return CredentialCompleteness::of($row);
        }

        return $this->snapshot->environmentFor($provider, $mode) instanceof ResolvedCredentials;
    }

    public function isActive(IntegrationProvider $provider, ?IntegrationMode $mode = null): bool
    {
        $row = $this->snapshot->row($provider, $mode);

        if ($row instanceof IntegrationCredential) {
            return $row->is_active;
        }

        return $this->snapshot->environmentFor($provider, $mode) instanceof ResolvedCredentials;
    }

    public function value(IntegrationProvider $provider, string $field, ?IntegrationMode $mode = null): mixed
    {
        $row = $this->snapshot->row($provider, $mode);

        if ($row instanceof IntegrationCredential) {
            return $row->publicValue($field);
        }

        return $this->snapshot->environmentFor($provider, $mode)?->publicConfig[$field] ?? null;
    }

    public function text(IntegrationProvider $provider, string $field, ?IntegrationMode $mode = null): ?string
    {
        $value = $this->value($provider, $field, $mode);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @return array{set: bool, last4: string|null, updated_at: string|null, updated_by: array{id: int, name: string}|null}
     */
    public function secret(IntegrationProvider $provider, string $field, ?IntegrationMode $mode = null): array
    {
        $row = $this->snapshot->row($provider, $mode);

        if ($row instanceof IntegrationCredential) {
            return $this->states->present($row->secret_meta, $field, $this->snapshot->actorNames);
        }

        return $this->states->presentEnvironment($this->snapshot->environmentFor($provider, $mode)?->hasSecret($field) === true);
    }

    /**
     * @return array{at: string|null, ok: bool, error_kind: string|null, latency_ms: int|null, by: array{id: int, name: string}|null}|null
     */
    public function lastTest(IntegrationProvider $provider, ?IntegrationMode $mode = null): ?array
    {
        $row = $this->snapshot->row($provider, $mode);

        return $row instanceof IntegrationCredential ? self::lastTestOf($row, $this->snapshot) : null;
    }

    /**
     * @return array{at: string|null, ok: bool, error_kind: string|null, latency_ms: int|null, by: array{id: int, name: string}|null}|null
     */
    public static function lastTestOf(IntegrationCredential $row, IntegrationSnapshot $snapshot): ?array
    {
        if ($row->last_test_ok === null) {
            return null;
        }

        return [
            'at' => $row->last_test_at?->toIso8601String(),
            'ok' => $row->last_test_ok,
            'error_kind' => $row->last_test_error_kind?->value,
            'latency_ms' => $row->last_test_latency_ms,
            'by' => self::actorOf($row->last_test_by_user_id, $snapshot),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public static function actorOf(mixed $userId, IntegrationSnapshot $snapshot): ?array
    {
        $id = ScalarValue::int($userId);
        $name = $snapshot->actorName($id);

        return $name === null ? null : ['id' => $id, 'name' => $name];
    }

    /**
     * @return array{at: string|null, by: array{id: int, name: string}|null}|null
     */
    public function stamp(mixed $stamp, string $atKey, string $byKey): ?array
    {
        if (! is_array($stamp) || ! is_string($stamp[$atKey] ?? null)) {
            return null;
        }

        return ['at' => $stamp[$atKey], 'by' => self::actorOf($stamp[$byKey] ?? null, $this->snapshot)];
    }
}
