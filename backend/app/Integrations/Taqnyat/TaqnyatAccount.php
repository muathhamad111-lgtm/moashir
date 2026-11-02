<?php

declare(strict_types=1);

namespace App\Integrations\Taqnyat;

use App\Contracts\Integrations\SmsAccountInspector;
use App\Contracts\Integrations\SmsSenderDirectory;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsBalance;
use App\Data\Integrations\SmsSender;
use App\Enums\Integrations\IntegrationProvider;
use App\Support\ScalarValue;

final readonly class TaqnyatAccount implements SmsAccountInspector, SmsSenderDirectory
{
    public function __construct(private TaqnyatClient $client) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Taqnyat;
    }

    public function balance(ResolvedCredentials $credentials): SmsBalance
    {
        $response = $this->client->get($credentials, 'account_balance', '/account/balance');

        return new SmsBalance(
            ScalarValue::string($response->json('balance'), '0'),
            $this->optional($response->json('currency')),
            $this->optional($response->json('accountStatus')),
            $this->optional($response->json('accountExpiryDate')),
        );
    }

    public function senders(ResolvedCredentials $credentials): array
    {
        $senders = $this->client->get($credentials, 'list_senders', '/v1/messages/senders')->json('senders');
        $list = [];

        foreach (is_array($senders) ? $senders : [] as $sender) {
            if (is_array($sender) && is_string($sender['senderName'] ?? null)) {
                $list[] = new SmsSender($sender['senderName'], ScalarValue::string($sender['status'] ?? null), $this->optional($sender['destination'] ?? null));
            }
        }

        return $list;
    }

    private function optional(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
