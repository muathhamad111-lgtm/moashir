<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Enums\Integrations\IntegrationProvider;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SecretValue;
use Carbon\CarbonInterface;

final class SecretStates
{
    /**
     * @return array<string, mixed>
     */
    public function metaFor(IntegrationProvider $provider, string $field, SecretValue $value, ?User $actor, CarbonInterface $at): array
    {
        return [
            'set' => true,
            'last4' => $provider->revealsLast4($field, $value->reveal()) ? $value->last4() : null,
            'updated_at' => $at->toIso8601String(),
            'updated_by_user_id' => $actor?->id,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $meta
     * @return array<string, array{set: true, last4: string|null}>
     */
    public function auditSummary(array $meta): array
    {
        $summary = [];

        foreach ($meta as $field => $state) {
            $last4 = $state['last4'] ?? null;
            $summary[$field] = ['set' => true, 'last4' => is_string($last4) ? $last4 : null];
        }

        ksort($summary);

        return $summary;
    }

    /**
     * @param  array<string, array<string, mixed>>  $meta
     * @param  array<int, string>  $actorNames
     * @return array{set: bool, last4: string|null, updated_at: string|null, updated_by: array{id: int, name: string}|null}
     */
    public function present(array $meta, string $field, array $actorNames): array
    {
        $state = $meta[$field] ?? null;

        if (! is_array($state)) {
            return ['set' => false, 'last4' => null, 'updated_at' => null, 'updated_by' => null];
        }

        $actorId = ScalarValue::int($state['updated_by_user_id'] ?? null);
        $last4 = $state['last4'] ?? null;
        $updatedAt = $state['updated_at'] ?? null;

        return [
            'set' => true,
            'last4' => is_string($last4) ? $last4 : null,
            'updated_at' => is_string($updatedAt) ? $updatedAt : null,
            'updated_by' => isset($actorNames[$actorId]) ? ['id' => $actorId, 'name' => $actorNames[$actorId]] : null,
        ];
    }

    /**
     * @return array{set: bool, last4: null, updated_at: null, updated_by: null}
     */
    public function presentEnvironment(bool $isSet): array
    {
        return ['set' => $isSet, 'last4' => null, 'updated_at' => null, 'updated_by' => null];
    }
}
