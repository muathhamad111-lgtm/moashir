<?php

declare(strict_types=1);

namespace App\Models\Integrations;

use App\Casts\JsonObjectCast;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property IntegrationKind $kind
 * @property IntegrationProvider $provider
 * @property IntegrationMode $mode
 * @property array<string, mixed> $public_config
 * @property array<string, string>|null $secrets
 * @property array<string, array<string, mixed>> $secret_meta
 * @property bool $is_active
 * @property int $config_version
 * @property Carbon|null $last_test_at
 * @property bool|null $last_test_ok
 * @property ProviderErrorKind|null $last_test_error_kind
 * @property int|null $last_test_latency_ms
 * @property int|null $last_test_by_user_id
 * @property array<string, mixed> $provider_refs
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class IntegrationCredential extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'public_config' => '{}',
        'secret_meta' => '{}',
        'provider_refs' => '{}',
        'is_active' => false,
        'config_version' => 1,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'provider', 'mode'];

    /**
     * @var list<string>
     */
    protected $hidden = ['secrets'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => IntegrationKind::class,
            'provider' => IntegrationProvider::class,
            'mode' => IntegrationMode::class,
            'public_config' => JsonObjectCast::class,
            'secrets' => 'encrypted:array',
            'secret_meta' => JsonObjectCast::class,
            'is_active' => 'boolean',
            'config_version' => 'integer',
            'last_test_at' => 'datetime',
            'last_test_ok' => 'boolean',
            'last_test_error_kind' => ProviderErrorKind::class,
            'last_test_latency_ms' => 'integer',
            'last_test_by_user_id' => 'integer',
            'provider_refs' => JsonObjectCast::class,
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofKind(Builder $query, IntegrationKind $kind): void
    {
        $query->where('kind', $kind->value);
    }

    public function hasPassingTest(): bool
    {
        return $this->last_test_ok === true;
    }

    public function publicValue(string $field): mixed
    {
        return $this->public_config[$field] ?? null;
    }

    public function providerRef(string $key): mixed
    {
        return $this->provider_refs[$key] ?? null;
    }
}
