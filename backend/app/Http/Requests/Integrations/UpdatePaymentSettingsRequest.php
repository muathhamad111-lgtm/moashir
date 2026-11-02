<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Data\Integrations\ActivationRequest;
use App\Data\Integrations\IntegrationUpdate;
use App\Data\Integrations\ProviderCredentialChanges;
use App\Enums\Integrations\CardNetwork;
use App\Enums\Integrations\CheckoutPattern;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\PaymentConfirmation;
use App\Enums\Integrations\PaymentLinkedPath;
use App\Enums\Integrations\PaymentMethod;
use App\Enums\Settings\SettingKey;
use App\Rules\SecretInput;
use App\Support\Integrations\CredentialInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePaymentSettingsRequest extends FormRequest
{
    private const array SETTINGS = [
        'methods' => SettingKey::PaymentMethods,
        'supported_networks' => SettingKey::PaymentSupportedNetworks,
        'apple_pay_label' => SettingKey::PaymentApplePayLabel,
        'linked_paths' => SettingKey::PaymentLinkedPaths,
        'checkout_pattern' => SettingKey::PaymentCheckoutPattern,
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', Rule::enum(IntegrationMode::class)],
            'active' => ['sometimes', 'boolean'],
            'checkout_pattern' => ['sometimes', Rule::enum(CheckoutPattern::class)],
            ...$this->keyPairRules(IntegrationMode::Test),
            ...$this->keyPairRules(IntegrationMode::Live),
            'methods' => ['sometimes', 'array', 'list'],
            'methods.*' => ['distinct', Rule::enum(PaymentMethod::class)],
            'supported_networks' => ['sometimes', 'array', 'list', 'min:1'],
            'supported_networks.*' => ['distinct', Rule::enum(CardNetwork::class)],
            'apple_pay_label' => ['sometimes', 'array:ar,en'],
            'apple_pay_label.ar' => ['required_with:apple_pay_label', 'string', 'min:1', 'max:64'],
            'apple_pay_label.en' => ['required_with:apple_pay_label', 'string', 'min:1', 'max:64'],
            'confirmations' => ['sometimes', 'array:apple_pay_domain,stcpay_activation'],
            'confirmations.apple_pay_domain' => ['sometimes', 'boolean'],
            'confirmations.stcpay_activation' => ['sometimes', 'boolean'],
            'linked_paths' => ['sometimes', 'array', 'list'],
            'linked_paths.*' => ['distinct', Rule::enum(PaymentLinkedPath::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): IntegrationUpdate
    {
        $validated = $this->validated();
        $settings = [];

        foreach (self::SETTINGS as $field => $key) {
            if (array_key_exists($field, $validated)) {
                $settings[$key->value] = $validated[$field];
            }
        }

        $confirmations = [];

        foreach (PaymentConfirmation::cases() as $confirmation) {
            if ($this->has('confirmations.'.$confirmation->value)) {
                $confirmations[$confirmation->value] = $this->boolean('confirmations.'.$confirmation->value);
            }
        }

        return new IntegrationUpdate(
            $this->has('mode') ? IntegrationMode::from($this->string('mode')->toString()) : null,
            $this->has('active') ? new ActivationRequest($this->boolean('active') ? IntegrationProvider::Moyasar : null) : null,
            array_map(fn (IntegrationMode $mode): ProviderCredentialChanges => new ProviderCredentialChanges(
                IntegrationProvider::Moyasar,
                $mode,
                CredentialInput::changes($validated, $mode->value.'.', ['publishable_key'], ['secret_key', 'webhook_secret']),
            ), IntegrationMode::cases()),
            $settings,
            $confirmations,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function keyPairRules(IntegrationMode $mode): array
    {
        $prefix = $mode->value;

        return [
            $prefix => ['sometimes', 'array:publishable_key,secret_key,webhook_secret'],
            $prefix.'.publishable_key' => ['sometimes', 'nullable', 'string', 'min:16', 'max:256', 'starts_with:pk_'.$prefix.'_', 'not_regex:/\s/'],
            $prefix.'.secret_key' => ['nullable', new SecretInput(16, 256, 'sk_'.$prefix.'_')],
            $prefix.'.webhook_secret' => ['nullable', new SecretInput(32, 128)],
        ];
    }
}
