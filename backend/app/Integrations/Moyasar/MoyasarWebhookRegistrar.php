<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Contracts\Integrations\PaymentWebhookRegistrar;
use App\Data\Integrations\RegisteredWebhook;
use App\Data\Integrations\ResolvedCredentials;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SecretValue;

final readonly class MoyasarWebhookRegistrar implements PaymentWebhookRegistrar
{
    public function __construct(private MoyasarClient $client) {}

    public function registeredFor(ResolvedCredentials $credentials, string $url): array
    {
        $webhooks = $this->client->get($credentials, 'list_webhooks', '/webhooks')->json('webhooks');
        $registered = [];

        foreach (is_array($webhooks) ? $webhooks : [] as $webhook) {
            if (is_array($webhook) && ($webhook['url'] ?? null) === $url && is_string($webhook['id'] ?? null)) {
                $registered[] = new RegisteredWebhook($webhook['id'], $url);
            }
        }

        return $registered;
    }

    public function register(ResolvedCredentials $credentials, string $url, SecretValue $sharedSecret): RegisteredWebhook
    {
        $response = $this->client->post($credentials, 'create_webhook', '/webhooks', [
            'http_method' => 'post',
            'url' => $url,
            'shared_secret' => $sharedSecret->reveal(),
        ]);

        return new RegisteredWebhook(ScalarValue::string($response->json('id')), ScalarValue::string($response->json('url'), $url));
    }

    public function remove(ResolvedCredentials $credentials, RegisteredWebhook $webhook): void
    {
        $this->client->delete($credentials, 'delete_webhook', '/webhooks/'.rawurlencode($webhook->id));
    }
}
