<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Data\Integrations\IntegrationSnapshot;
use App\Enums\Integrations\AnthropicInferenceGeo;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\OpenAiRegion;
use App\Enums\Settings\SettingKey;
use App\Services\Integrations\IntegrationActivation;
use App\Support\Integrations\CredentialPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IntegrationSnapshot $resource
 */
final class AiSettingsResource extends JsonResource
{
    public const int DEFAULT_MAX_OUTPUT_TOKENS = 1024;

    public const int DEFAULT_TIMEOUT_SECONDS = 30;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $presenter = new CredentialPresenter($snapshot);

        return [
            'active_provider' => $snapshot->activeProvider()?->value,
            'mode' => $snapshot->mode()->value,
            'source' => $snapshot->source->value,
            'placement' => $snapshot->setting(SettingKey::AiPlacement),
            'notice' => $snapshot->setting(SettingKey::AiNotice),
            'providers' => [
                'openai' => [
                    ...$this->common($presenter, IntegrationProvider::OpenAi),
                    'region' => $presenter->text(IntegrationProvider::OpenAi, 'region') ?? OpenAiRegion::Global->value,
                    'organization_id' => $presenter->text(IntegrationProvider::OpenAi, 'organization_id'),
                    'project_id' => $presenter->text(IntegrationProvider::OpenAi, 'project_id'),
                ],
                'anthropic' => [
                    ...$this->common($presenter, IntegrationProvider::Anthropic),
                    'workspace_id' => $presenter->text(IntegrationProvider::Anthropic, 'workspace_id'),
                    'inference_geo' => $presenter->text(IntegrationProvider::Anthropic, 'inference_geo') ?? AnthropicInferenceGeo::Global->value,
                ],
            ],
            'checklist' => [
                ['key' => 'provider_account_and_billing', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'spend_limits', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'data_retention_contract', 'confirmable' => false, 'confirmed' => null],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function common(CredentialPresenter $presenter, IntegrationProvider $provider): array
    {
        $row = $this->resource->row($provider);
        $acknowledgement = $presenter->stamp($row?->provider_refs, IntegrationActivation::ACKNOWLEDGED_AT, 'data_transfer_acknowledged_by');

        return [
            'configured' => $presenter->isConfigured($provider),
            'api_key' => $presenter->secret($provider, 'api_key'),
            'model' => $presenter->text($provider, 'model'),
            'max_output_tokens' => (int) ($presenter->text($provider, 'max_output_tokens') ?? self::DEFAULT_MAX_OUTPUT_TOKENS),
            'timeout_seconds' => (int) ($presenter->text($provider, 'timeout_seconds') ?? self::DEFAULT_TIMEOUT_SECONDS),
            'data_transfer_acknowledgement' => [
                'acknowledged' => $acknowledgement !== null,
                'acknowledged_at' => $acknowledgement['at'] ?? null,
                'acknowledged_by' => $acknowledgement['by'] ?? null,
            ],
            'last_test' => $presenter->lastTest($provider),
        ];
    }
}
