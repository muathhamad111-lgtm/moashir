<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Settings\IntegrationSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class IntegrationRows
{
    public function __construct(private IntegrationSettings $settings) {}

    /**
     * @return Collection<int, IntegrationCredential>
     */
    public function lockKind(IntegrationKind $kind): Collection
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['integration_credentials:'.$kind->value]);

        return IntegrationCredential::query()->ofKind($kind)->orderBy('id')->lockForUpdate()->get();
    }

    public function find(IntegrationProvider $provider, ?IntegrationMode $mode = null): ?IntegrationCredential
    {
        $kind = $provider->kind();

        return IntegrationCredential::query()
            ->ofKind($kind)
            ->where('provider', $provider->value)
            ->when($kind->hasModePerCredential(), fn ($query) => $query->where('mode', ($mode ?? $this->settings->mode($kind))->value))
            ->first();
    }

    public function findOrNew(IntegrationProvider $provider, ?IntegrationMode $mode = null): IntegrationCredential
    {
        $kind = $provider->kind();
        $rowMode = $kind->hasModePerCredential() && $mode instanceof IntegrationMode ? $mode : $this->settings->mode($kind);

        return $this->find($provider, $rowMode) ?? new IntegrationCredential([
            'kind' => $kind,
            'provider' => $provider,
            'mode' => $rowMode,
        ]);
    }

    public function active(IntegrationKind $kind): ?IntegrationCredential
    {
        return IntegrationCredential::query()->ofKind($kind)->where('is_active', true)->first();
    }
}
