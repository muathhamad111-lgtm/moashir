<?php

declare(strict_types=1);

namespace App\Integrations\Msegat;

use App\Contracts\Integrations\SmsProviderDriver;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;
use App\Enums\Integrations\IntegrationProvider;

final readonly class MsegatSmsGateway implements SmsProviderDriver
{
    private const string OPERATION = 'send_sms';

    private const array MESSAGE_ID_KEYS = ['BulkId', 'bulkId', 'bulk_id', 'id'];

    public function __construct(private MsegatClient $client) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Msegat;
    }

    public function send(ResolvedCredentials $credentials, SmsMessage $message): SmsSendResult
    {
        $response = $this->client->postJson($credentials, self::OPERATION, 'sendsms.php', [
            'numbers' => $message->internationalNumber(),
            'userSender' => $credentials->string('sender_name'),
            'msg' => $message->text,
            'msgEncoding' => 'UTF8',
            'reqBulkId' => 'true',
        ]);

        if (! $response->successful() || ! MsegatResponse::isSuccess(MsegatResponse::code($response))) {
            throw MsegatResponse::failure($response, self::OPERATION);
        }

        return SmsSendResult::sent($this->provider(), $this->messageId($response->json()));
    }

    private function messageId(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        foreach (self::MESSAGE_ID_KEYS as $key) {
            if (is_scalar($body[$key] ?? null) && (string) $body[$key] !== '') {
                return (string) $body[$key];
            }
        }

        return null;
    }
}
