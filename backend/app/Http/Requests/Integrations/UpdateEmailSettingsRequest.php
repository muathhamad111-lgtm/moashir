<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Data\Integrations\ActivationRequest;
use App\Data\Integrations\IntegrationUpdate;
use App\Data\Integrations\ProviderCredentialChanges;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmtpEncryption;
use App\Enums\Settings\SettingKey;
use App\Rules\Hostname;
use App\Rules\SecretInput;
use App\Services\Integrations\SmtpHostGuard;
use App\Support\Integrations\CredentialInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEmailSettingsRequest extends FormRequest
{
    private const array PUBLIC_FIELDS = [
        'host', 'port', 'encryption', 'username', 'from_address', 'from_name', 'reply_to', 'ehlo_domain', 'timeout_seconds',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['sometimes', 'boolean'],
            'mode' => ['sometimes', Rule::enum(IntegrationMode::class)],
            'host' => ['sometimes', 'nullable', 'string', 'max:253', new Hostname],
            'port' => ['sometimes', 'integer', Rule::in(SmtpHostGuard::ALLOWED_PORTS)],
            'encryption' => ['sometimes', Rule::enum(SmtpEncryption::class)],
            'username' => ['sometimes', 'nullable', 'string', 'min:1', 'max:255'],
            'password' => ['nullable', new SecretInput(1, 255, null, true)],
            'from_address' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'from_name' => ['sometimes', 'nullable', 'array:ar,en'],
            'from_name.ar' => ['required_with:from_name', 'string', 'min:1', 'max:100'],
            'from_name.en' => ['required_with:from_name', 'string', 'min:1', 'max:100'],
            'reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'ehlo_domain' => ['sometimes', 'nullable', 'string', 'max:253', new Hostname],
            'timeout_seconds' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'test_address' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): IntegrationUpdate
    {
        $validated = $this->validated();
        $settings = array_key_exists('test_address', $validated) ? [SettingKey::EmailTestAddress->value => $validated['test_address']] : [];

        return new IntegrationUpdate(
            $this->has('mode') ? IntegrationMode::from($this->string('mode')->toString()) : null,
            $this->has('active') ? new ActivationRequest($this->boolean('active') ? IntegrationProvider::Smtp : null) : null,
            [new ProviderCredentialChanges(IntegrationProvider::Smtp, null, CredentialInput::changes($validated, '', self::PUBLIC_FIELDS, ['password']))],
            $settings,
            [],
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
