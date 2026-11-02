<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Data\Integrations\PaymentSettingsView;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\PaymentConfirmation;
use App\Enums\Settings\SettingKey;
use App\Support\Integrations\CredentialPresenter;
use App\Support\Integrations\IntegrationUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentSettingsView $resource
 */
final class PaymentSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource->snapshot;
        $presenter = new CredentialPresenter($snapshot);
        $urls = app(IntegrationUrls::class);

        return [
            'provider' => IntegrationProvider::Moyasar->value,
            'mode' => $snapshot->mode()->value,
            'active' => $snapshot->isActive(),
            'source' => $snapshot->source->value,
            'is_test_mode_in_production' => $snapshot->isProduction && $snapshot->mode()->isTest(),
            'checkout_pattern' => $snapshot->setting(SettingKey::PaymentCheckoutPattern),
            'test' => $this->keyPair($presenter, IntegrationMode::Test),
            'live' => $this->keyPair($presenter, IntegrationMode::Live),
            'methods' => $snapshot->setting(SettingKey::PaymentMethods),
            'supported_networks' => $snapshot->setting(SettingKey::PaymentSupportedNetworks),
            'apple_pay_label' => $snapshot->setting(SettingKey::PaymentApplePayLabel),
            'confirmations' => $this->confirmations($presenter),
            'linked_paths' => $snapshot->setting(SettingKey::PaymentLinkedPaths),
            'apple_pay_association_file' => new ApplePayAssociationFileResource($this->resource->applePayAssociationFile)->toArray($request),
            'values_to_copy' => [
                'webhook_url' => $urls->moyasarWebhook(),
                'webhook_method' => 'POST',
                'webhook_events' => 'all',
                'apple_pay_host' => $urls->host(),
                'apple_pay_association_url' => $urls->applePayAssociation(),
                'egress_ips' => $urls->egressIps(),
            ],
            'checklist' => [
                ['key' => 'live_account_activation', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'apple_pay_domain', 'confirmable' => true, 'confirmed' => $this->isConfirmed(PaymentConfirmation::ApplePayDomain)],
                ['key' => 'stcpay_activation', 'confirmable' => true, 'confirmed' => $this->isConfirmed(PaymentConfirmation::StcPayActivation)],
                ['key' => 'ip_whitelist', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'manual_webhook', 'confirmable' => false, 'confirmed' => null],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function keyPair(CredentialPresenter $presenter, IntegrationMode $mode): array
    {
        $provider = IntegrationProvider::Moyasar;
        $row = $presenter->snapshot()->row($provider, $mode);
        $webhookId = $row?->providerRef('webhook_id');

        return [
            'configured' => $presenter->isConfigured($provider, $mode),
            'active' => $presenter->snapshot()->isActive() && $presenter->snapshot()->mode() === $mode,
            'publishable_key' => $presenter->text($provider, 'publishable_key', $mode),
            'secret_key' => $presenter->secret($provider, 'secret_key', $mode),
            'webhook_secret' => $presenter->secret($provider, 'webhook_secret', $mode),
            'webhook' => is_string($webhookId) ? [
                'id' => $webhookId,
                'url' => $row?->providerRef('webhook_url'),
                'registered_at' => $row?->providerRef('webhook_registered_at'),
            ] : null,
            'last_test' => $presenter->lastTest($provider, $mode),
        ];
    }

    /**
     * @return array<string, array{confirmed: bool, confirmed_at: string|null, confirmed_by: array{id: int, name: string}|null}>
     */
    private function confirmations(CredentialPresenter $presenter): array
    {
        $confirmations = [];

        foreach (PaymentConfirmation::cases() as $confirmation) {
            $stamp = $presenter->stamp($this->resource->snapshot->setting($confirmation->settingKey()), 'confirmed_at', 'confirmed_by_user_id');
            $confirmations[$confirmation->value] = [
                'confirmed' => $stamp !== null,
                'confirmed_at' => $stamp['at'] ?? null,
                'confirmed_by' => $stamp['by'] ?? null,
            ];
        }

        return $confirmations;
    }

    private function isConfirmed(PaymentConfirmation $confirmation): bool
    {
        return is_array($this->resource->snapshot->setting($confirmation->settingKey()));
    }
}
