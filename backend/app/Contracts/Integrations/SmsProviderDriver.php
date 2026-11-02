<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;

interface SmsProviderDriver extends ServesProvider
{
    public function send(ResolvedCredentials $credentials, SmsMessage $message): SmsSendResult;
}
