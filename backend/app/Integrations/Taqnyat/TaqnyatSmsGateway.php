<?php

declare(strict_types=1);

namespace App\Integrations\Taqnyat;

use App\Contracts\Integrations\SmsProviderDriver;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;

final readonly class TaqnyatSmsGateway implements SmsProviderDriver
{
    private const string OPERATION = 'send_sms';

    public function __construct(private TaqnyatClient $client) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Taqnyat;
    }

    public function send(ResolvedCredentials $credentials, SmsMessage $message): SmsSendResult
    {
        $number = $message->internationalNumber();
        $response = $this->client->post($credentials, self::OPERATION, '/v1/messages', [
            'recipients' => [$number],
            'body' => $message->text,
            'sender' => $credentials->string('sender_name'),
        ]);

        $rejected = $response->json('rejected');

        if (is_string($rejected) && str_contains($rejected, $number)) {
            throw ProviderCallFailed::because($this->provider(), self::OPERATION, ProviderErrorKind::RecipientInvalid, $response->status());
        }

        $messageId = $response->json('messageId');
        $segments = $response->json('msgLength');

        return SmsSendResult::sent($this->provider(), is_scalar($messageId) ? (string) $messageId : null, is_int($segments) ? $segments : null);
    }
}
