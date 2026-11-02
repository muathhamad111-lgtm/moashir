<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\RegisteredWebhook;
use App\Data\Integrations\ResolvedCredentials;
use App\Support\ValueObjects\SecretValue;

interface PaymentWebhookRegistrar
{
    /**
     * @return list<RegisteredWebhook>
     */
    public function registeredFor(ResolvedCredentials $credentials, string $url): array;

    public function register(ResolvedCredentials $credentials, string $url, SecretValue $sharedSecret): RegisteredWebhook;

    public function remove(ResolvedCredentials $credentials, RegisteredWebhook $webhook): void;
}
