<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Contracts\Integrations\IntegrationConnectionTester;
use App\Contracts\Integrations\PaymentWebhookRegistrar;
use App\Data\Integrations\ConnectionCheck;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\CheckoutPattern;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\ProviderHttp;
use App\Services\Settings\SettingsStore;
use App\Support\Integrations\IntegrationUrls;
use App\Support\ScalarValue;

final readonly class MoyasarConnectionTester implements IntegrationConnectionTester
{
    private const int TEST_INTENT_HALALAS = 100;

    private const string TEST_INTENT_DESCRIPTION = 'Moashir connection test';

    public function __construct(
        private MoyasarClient $client,
        private PaymentWebhookRegistrar $webhooks,
        private SettingsStore $settings,
        private IntegrationUrls $urls,
    ) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Moyasar;
    }

    public function test(ResolvedCredentials $credentials): ConnectionTestResult
    {
        $startedAt = (int) hrtime(true);

        if (! $this->prefixesMatch($credentials)) {
            return $this->result($credentials, $startedAt, [ConnectionCheck::failed('key_prefixes', 'prefix_mismatch')], [], ProviderErrorKind::Misconfigured);
        }

        $checks = [ConnectionCheck::passed('key_prefixes')];

        try {
            $registered = $this->webhooks->registeredFor($credentials, $this->urls->moyasarWebhook()) !== [];
            $checks[] = ConnectionCheck::passed('secret_key_valid');
            $info = ['webhook_registered' => $registered, 'publishable_key_check' => 'validated_at_first_checkout'];

            if ($this->usesIntents()) {
                $this->createAndCancelIntent($credentials);
                $checks[] = ConnectionCheck::passed('intents_available');
            }
        } catch (ProviderCallFailed $failure) {
            $checks[] = ConnectionCheck::failed(count($checks) === 1 ? 'secret_key_valid' : 'intents_available', $failure->kind->value);

            return $this->result($credentials, $startedAt, $checks, $info ?? [], $failure->kind);
        }

        return $this->result($credentials, $startedAt, $checks, $info);
    }

    private function prefixesMatch(ResolvedCredentials $credentials): bool
    {
        $mode = $credentials->mode->value;

        return str_starts_with($credentials->string('publishable_key'), 'pk_'.$mode.'_')
            && str_starts_with($credentials->secret('secret_key')->reveal(), 'sk_'.$mode.'_');
    }

    private function usesIntents(): bool
    {
        return $this->settings->get(SettingKey::PaymentCheckoutPattern) === CheckoutPattern::Intent->value;
    }

    private function createAndCancelIntent(ResolvedCredentials $credentials): void
    {
        $intent = $this->client->post($credentials, 'test_create_payment_intent', '/payment_intents', [
            'amount' => self::TEST_INTENT_HALALAS,
            'currency' => 'SAR',
            'description' => self::TEST_INTENT_DESCRIPTION,
            'callback_url' => $this->urls->paymentCallback('connection-test'),
        ]);

        $this->client->post($credentials, 'test_cancel_payment_intent', '/payment_intents/'.rawurlencode(ScalarValue::string($intent->json('id'))).'/cancel');
    }

    /**
     * @param  list<ConnectionCheck>  $checks
     * @param  array<string, scalar|null>  $info
     */
    private function result(ResolvedCredentials $credentials, int $startedAt, array $checks, array $info, ?ProviderErrorKind $error = null): ConnectionTestResult
    {
        return new ConnectionTestResult($this->provider(), $credentials->mode, ProviderHttp::elapsedMilliseconds($startedAt), $checks, $info, $error);
    }
}
