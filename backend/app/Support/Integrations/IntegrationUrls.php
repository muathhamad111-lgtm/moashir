<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use Illuminate\Contracts\Config\Repository;

final readonly class IntegrationUrls
{
    public const string MOYASAR_WEBHOOK_PATH = '/api/v1/webhooks/moyasar';

    public const string APPLE_PAY_ASSOCIATION_PATH = '/.well-known/apple-developer-merchantid-domain-association';

    public const string MOYASAR_APPLE_PAY_VALIDATION_URL = 'https://api.moyasar.com/v1/applepay/initiate';

    public function __construct(private Repository $config) {}

    public function moyasarWebhook(): string
    {
        return $this->base().self::MOYASAR_WEBHOOK_PATH;
    }

    public function applePayAssociation(): string
    {
        return 'https://'.$this->host().self::APPLE_PAY_ASSOCIATION_PATH;
    }

    public function host(): string
    {
        return (string) parse_url($this->base(), PHP_URL_HOST);
    }

    public function isHttps(): bool
    {
        return str_starts_with($this->base(), 'https://');
    }

    public function paymentCallback(string $paymentId): string
    {
        return rtrim($this->config->string('moashir.frontend_url'), '/').'/payments/'.$paymentId.'/return';
    }

    /**
     * @return list<string>
     */
    public function egressIps(): array
    {
        $ips = $this->config->get('moashir.integrations.egress_ips');

        return is_array($ips) ? array_values(array_filter($ips, is_string(...))) : [];
    }

    private function base(): string
    {
        return rtrim($this->config->string('app.url'), '/');
    }
}
