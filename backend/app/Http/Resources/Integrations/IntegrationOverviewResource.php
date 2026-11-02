<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Data\Integrations\IntegrationSnapshot;
use App\Enums\Integrations\IntegrationProvider;
use App\Models\Integrations\IntegrationCredential;
use App\Support\Integrations\CredentialCompleteness;
use App\Support\Integrations\CredentialPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IntegrationSnapshot $resource
 */
final class IntegrationOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $tested = $this->representativeRow();

        return [
            'kind' => $snapshot->kind->value,
            'provider' => $this->provider()?->value,
            'active' => $snapshot->isActive(),
            'mode' => $snapshot->mode()->value,
            'source' => $snapshot->source->value,
            'configured' => $snapshot->environment !== null || array_any($snapshot->rows, CredentialCompleteness::of(...)),
            'last_test' => $tested instanceof IntegrationCredential ? CredentialPresenter::lastTestOf($tested, $snapshot) : null,
        ];
    }

    private function provider(): ?IntegrationProvider
    {
        $providers = $this->resource->kind->providers();

        return $this->resource->activeProvider() ?? (count($providers) === 1 ? $providers[0] : null);
    }

    private function representativeRow(): ?IntegrationCredential
    {
        $snapshot = $this->resource;
        $active = $snapshot->activeRow();

        if ($active instanceof IntegrationCredential) {
            return $active;
        }

        $tested = array_filter($snapshot->rows, static fn (IntegrationCredential $row): bool => $row->last_test_at !== null);
        usort($tested, static fn (IntegrationCredential $left, IntegrationCredential $right): int => $right->last_test_at <=> $left->last_test_at);

        return $tested[0] ?? null;
    }
}
