<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\PaymentCheckout;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\CheckoutPattern;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\PaymentMethod;
use App\Enums\Settings\SettingKey;
use App\Models\Finance\Payment;
use App\Services\Integrations\IntegrationCredentialsResolver;
use App\Services\Settings\SettingsStore;
use App\Support\Integrations\IntegrationUrls;
use App\Support\StringList;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\SecretValue;
use Illuminate\Contracts\Config\Repository;

final readonly class CheckoutPresenter
{
    private const string APPLE_PAY_COUNTRY = 'SA';

    public function __construct(
        private IntegrationCredentialsResolver $resolver,
        private SettingsStore $settings,
        private IntegrationUrls $urls,
        private Repository $config,
    ) {}

    public function present(Payment $payment, string $locale): ?PaymentCheckout
    {
        $secret = $payment->checkout_client_secret;

        if ($payment->status !== PaymentStatus::Created || $payment->provider_intent_id === null || $secret === null || $payment->expires_at->isPast()) {
            return null;
        }

        $credentials = $this->resolver->forProvider(IntegrationProvider::Moyasar, $payment->mode);

        if (! $credentials->isConfigured()) {
            return null;
        }

        $settings = $this->settings->many([SettingKey::PaymentMethods, SettingKey::PaymentSupportedNetworks, SettingKey::PaymentApplePayLabel]);
        $methods = StringList::from($settings[SettingKey::PaymentMethods->value]);

        return new PaymentCheckout(
            CheckoutPattern::Intent,
            $payment->mode,
            $credentials->string('publishable_key'),
            $payment->totalMoney()->inMinorUnits(),
            $payment->currency,
            self::descriptionOf($payment),
            $this->urls->paymentCallback($payment->public_id),
            $locale === 'en' ? 'en' : 'ar',
            $methods,
            StringList::from($settings[SettingKey::PaymentSupportedNetworks->value]),
            $this->metadataOf($payment),
            $payment->provider_intent_id,
            new SecretValue($secret),
            in_array(PaymentMethod::ApplePay->value, $methods, true) ? $this->applePay($settings[SettingKey::PaymentApplePayLabel->value], $locale) : null,
            $payment->expires_at,
        );
    }

    public static function descriptionOf(Payment $payment): string
    {
        return 'Moashir '.$payment->reference;
    }

    /**
     * @return array<string, string>
     */
    public function metadataOf(Payment $payment): array
    {
        return [
            'moashir_payment_id' => $payment->public_id,
            'moashir_reference' => $payment->reference,
            'purpose' => $payment->purpose->value,
            'app_env' => $this->config->string('app.env'),
        ];
    }

    /**
     * @return array{country: string, label: string, validate_merchant_url: string}
     */
    private function applePay(mixed $label, string $locale): array
    {
        $text = is_array($label) ? LocalizedText::fromArray($label) : LocalizedText::fromTranslation('integrations.defaults.apple_pay_label');

        return [
            'country' => self::APPLE_PAY_COUNTRY,
            'label' => $text->in($locale),
            'validate_merchant_url' => IntegrationUrls::MOYASAR_APPLE_PAY_VALIDATION_URL,
        ];
    }
}
