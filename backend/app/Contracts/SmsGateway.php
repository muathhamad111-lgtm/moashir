<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;

interface SmsGateway
{
    public function send(SmsMessage $message): SmsSendResult;
}
