<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\SmsGateway;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;
use App\Enums\Integrations\SmsDeliveryStatus;

final class FakeSmsGateway implements SmsGateway
{
    /**
     * @var list<array{recipient: string, message: string, purpose: string}>
     */
    private array $sent = [];

    public function send(SmsMessage $message): SmsSendResult
    {
        $this->sent[] = ['recipient' => $message->recipient->value, 'message' => $message->text, 'purpose' => $message->purpose->value];

        return new SmsSendResult(null, SmsDeliveryStatus::Sent, 'fake-'.count($this->sent));
    }

    /**
     * @return list<array{recipient: string, message: string, purpose: string}>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
