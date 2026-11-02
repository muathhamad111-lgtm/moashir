<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Contracts\Integrations\PaymentGateway;
use App\Data\Integrations\CheckoutSession;
use App\Data\Integrations\PaymentCheckoutRequest;
use App\Data\Integrations\ProviderPayment;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Services\Integrations\IntegrationCredentialsResolver;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\SecretValue;
use Carbon\CarbonImmutable;

final readonly class MoyasarPaymentGateway implements PaymentGateway
{
    private const int FETCH_RETRIES = 3;

    private const int CREATE_RETRIES = 2;

    public function __construct(private MoyasarClient $client, private IntegrationCredentialsResolver $resolver) {}

    public function createCheckout(PaymentCheckoutRequest $request): CheckoutSession
    {
        $credentials = $this->resolver->forProvider(IntegrationProvider::Moyasar, $request->mode);
        $response = $this->client->post($credentials, 'create_payment_intent', '/payment_intents', [
            'amount' => MoyasarAmount::toHalalas($request->amount),
            'currency' => $request->amount->currency,
            'description' => $request->description,
            'callback_url' => $request->callbackUrl,
        ], self::CREATE_RETRIES);

        $expiresAt = $response->json('expires_at');

        return new CheckoutSession(
            $request->mode,
            ScalarValue::string($response->json('id')),
            new SecretValue(ScalarValue::string($response->json('client_secret'), '-')),
            ScalarValue::string($response->json('status')),
            MoyasarAmount::fromHalalas($response->json('amount'), ScalarValue::string($response->json('currency'), Money::SAUDI_RIYAL)),
            is_string($expiresAt) ? CarbonImmutable::parse($expiresAt) : null,
        );
    }

    public function fetchPayment(IntegrationMode $mode, string $providerPaymentId): ProviderPayment
    {
        $credentials = $this->resolver->forProvider(IntegrationProvider::Moyasar, $mode);
        $payment = $this->client->get($credentials, 'fetch_payment', '/payments/'.rawurlencode($providerPaymentId), [], self::FETCH_RETRIES);

        $payload = $payment->json();

        return MoyasarPaymentMapper::fromPayload(is_array($payload) ? $payload : []);
    }

    public function cancelCheckout(IntegrationMode $mode, string $intentId): void
    {
        $credentials = $this->resolver->forProvider(IntegrationProvider::Moyasar, $mode);

        $this->client->post($credentials, 'cancel_payment_intent', '/payment_intents/'.rawurlencode($intentId).'/cancel');
    }
}
