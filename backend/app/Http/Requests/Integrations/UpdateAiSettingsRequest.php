<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Data\Integrations\ActivationRequest;
use App\Data\Integrations\IntegrationUpdate;
use App\Data\Integrations\ProviderCredentialChanges;
use App\Enums\Integrations\AnthropicInferenceGeo;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\OpenAiRegion;
use App\Enums\Settings\SettingKey;
use App\Rules\SecretInput;
use App\Support\Integrations\CredentialInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAiSettingsRequest extends FormRequest
{
    public const array PLACEMENTS = ['public_only', 'platform_only', 'public_and_platform'];

    private const array OPENAI_FIELDS = ['region', 'organization_id', 'project_id', 'model', 'max_output_tokens', 'timeout_seconds'];

    private const array ANTHROPIC_FIELDS = ['workspace_id', 'inference_geo', 'model', 'max_output_tokens', 'timeout_seconds'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active_provider' => ['sometimes', 'nullable', Rule::in(IntegrationProvider::valuesOf(IntegrationKind::Ai))],
            'mode' => ['sometimes', Rule::enum(IntegrationMode::class)],
            'placement' => ['sometimes', Rule::in(self::PLACEMENTS)],
            'notice' => ['sometimes', 'array:ar,en'],
            'notice.ar' => ['required_with:notice', 'string', 'min:1', 'max:500'],
            'notice.en' => ['required_with:notice', 'string', 'min:1', 'max:500'],
            'providers' => ['sometimes', 'array:openai,anthropic'],
            'providers.openai' => ['sometimes', 'array:api_key,data_transfer_acknowledged,'.implode(',', self::OPENAI_FIELDS)],
            'providers.openai.region' => ['sometimes', Rule::enum(OpenAiRegion::class)],
            'providers.openai.organization_id' => ['sometimes', 'nullable', 'string', 'max:100', 'not_regex:/\s/'],
            'providers.openai.project_id' => ['sometimes', 'nullable', 'string', 'max:100', 'not_regex:/\s/'],
            'providers.anthropic' => ['sometimes', 'array:api_key,data_transfer_acknowledged,'.implode(',', self::ANTHROPIC_FIELDS)],
            'providers.anthropic.workspace_id' => ['sometimes', 'nullable', 'string', 'max:100', 'starts_with:wrkspc_', 'not_regex:/\s/'],
            'providers.anthropic.inference_geo' => ['sometimes', Rule::enum(AnthropicInferenceGeo::class)],
            ...$this->commonProviderRules('openai'),
            ...$this->commonProviderRules('anthropic'),
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): IntegrationUpdate
    {
        $validated = $this->validated();
        $acknowledgements = [];

        foreach ([IntegrationProvider::OpenAi, IntegrationProvider::Anthropic] as $provider) {
            $key = 'providers.'.$provider->value.'.data_transfer_acknowledged';

            if ($this->has($key)) {
                $acknowledgements[$provider->value] = $this->boolean($key);
            }
        }

        return new IntegrationUpdate(
            $this->has('mode') ? IntegrationMode::from($this->string('mode')->toString()) : null,
            $this->has('active_provider') ? new ActivationRequest(IntegrationProvider::tryFrom($this->string('active_provider')->toString())) : null,
            [
                new ProviderCredentialChanges(IntegrationProvider::OpenAi, null, CredentialInput::changes($validated, 'providers.openai.', self::OPENAI_FIELDS, ['api_key'])),
                new ProviderCredentialChanges(IntegrationProvider::Anthropic, null, CredentialInput::changes($validated, 'providers.anthropic.', self::ANTHROPIC_FIELDS, ['api_key'])),
            ],
            $this->settings($validated),
            $acknowledgements,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function commonProviderRules(string $provider): array
    {
        $prefix = 'providers.'.$provider.'.';

        return [
            $prefix.'api_key' => ['nullable', new SecretInput(20, 512)],
            $prefix.'model' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            $prefix.'max_output_tokens' => ['sometimes', 'integer', 'min:64', 'max:4096'],
            $prefix.'timeout_seconds' => ['sometimes', 'integer', 'min:5', 'max:60'],
            $prefix.'data_transfer_acknowledged' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settings(array $validated): array
    {
        $settings = [];

        if (array_key_exists('placement', $validated)) {
            $settings[SettingKey::AiPlacement->value] = $validated['placement'];
        }

        if (array_key_exists('notice', $validated)) {
            $settings[SettingKey::AiNotice->value] = $validated['notice'];
        }

        return $settings;
    }
}
