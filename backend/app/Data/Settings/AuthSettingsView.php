<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Identity\OtpChannel;
use App\Enums\Integrations\SmsGatewayStatus;

final readonly class AuthSettingsView
{
    /**
     * @param  list<OtpChannel>  $entitySignInCodeChannels
     */
    public function __construct(public array $entitySignInCodeChannels, public SmsGatewayStatus $smsStatus) {}
}
