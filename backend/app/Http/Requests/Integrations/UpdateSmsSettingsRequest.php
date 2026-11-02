<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Data\Integrations\ActivationRequest;
use App\Data\Integrations\IntegrationUpdate;
use App\Data\Integrations\ProviderCredentialChanges;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmsTemplateKey;
use App\Enums\Settings\SettingKey;
use App\Rules\SecretInput;
use App\Support\Integrations\CredentialInput;
use App\Support\Integrations\SmsTemplateRules;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSmsSettingsRequest extends FormRequest
{
    private const int MAX_TEST_RECIPIENTS = 5;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active_provider' => ['sometimes', 'nullable', Rule::in(IntegrationProvider::valuesOf(IntegrationProvider::Taqnyat->kind()))],
            'mode' => ['sometimes', Rule::enum(IntegrationMode::class)],
            'test_recipients' => ['sometimes', 'array', 'list', 'max:'.self::MAX_TEST_RECIPIENTS],
            'test_recipients.*' => ['string', 'distinct', 'regex:'.PhoneNumber::SAUDI_MOBILE_PATTERN],
            'providers' => ['sometimes', 'array:taqnyat,msegat'],
            'providers.taqnyat' => ['sometimes', 'array:bearer_token,sender_name'],
            'providers.taqnyat.bearer_token' => ['nullable', new SecretInput(20, 512)],
            'providers.taqnyat.sender_name' => ['sometimes', 'nullable', 'string', 'min:1', 'max:50'],
            'providers.msegat' => ['sometimes', 'array:username,api_key,sender_name,template_restricted'],
            'providers.msegat.username' => ['sometimes', 'nullable', 'string', 'min:1', 'max:100'],
            'providers.msegat.api_key' => ['nullable', new SecretInput(16, 256)],
            'providers.msegat.sender_name' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{1,11}$/'],
            'providers.msegat.template_restricted' => ['sometimes', 'boolean'],
            'templates' => ['sometimes', 'array', 'list'],
            'templates.*.key' => ['required', 'distinct', Rule::enum(SmsTemplateKey::class)],
            'templates.*.body' => ['required', 'array:ar,en'],
            'templates.*.body.ar' => ['required', 'string', 'max:'.SmsTemplateRules::MAX_LENGTH],
            'templates.*.body.en' => ['required', 'string', 'max:'.SmsTemplateRules::MAX_LENGTH],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [static function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            SmsTemplateRules::validate($validator);
        }];
    }

    public function toUpdate(): IntegrationUpdate
    {
        $validated = $this->validated();

        return new IntegrationUpdate(
            $this->has('mode') ? IntegrationMode::from($this->string('mode')->toString()) : null,
            $this->has('active_provider') ? new ActivationRequest(IntegrationProvider::tryFrom($this->string('active_provider')->toString())) : null,
            [
                new ProviderCredentialChanges(IntegrationProvider::Taqnyat, null, CredentialInput::changes($validated, 'providers.taqnyat.', ['sender_name'], ['bearer_token'])),
                new ProviderCredentialChanges(IntegrationProvider::Msegat, null, CredentialInput::changes($validated, 'providers.msegat.', ['username', 'sender_name', 'template_restricted'], ['api_key'])),
            ],
            $this->settings($validated),
            [],
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    /**
     * @param  array<array-key, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settings(array $validated): array
    {
        $settings = [];

        if (array_key_exists('test_recipients', $validated)) {
            $settings[SettingKey::SmsTestRecipients->value] = $validated['test_recipients'];
        }

        if (array_key_exists('templates', $validated) && is_array($validated['templates'])) {
            $settings[SettingKey::SmsTemplates->value] = SmsTemplateRules::keyed($validated['templates']);
        }

        return $settings;
    }
}
