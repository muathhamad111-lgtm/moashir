<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Settings\AuthSettingsView;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\SmsGatewayStatus;
use App\Services\Identity\SignInCodeChannels;

final readonly class AuthSettingsReader
{
    public function __construct(
        private SignInCodeChannels $codeChannels,
        private SmsAvailability $sms,
        private IntegrationSettings $integrations,
    ) {}

    public function read(): AuthSettingsView
    {
        return new AuthSettingsView($this->codeChannels->allowed(), $this->smsStatus());
    }

    private function smsStatus(): SmsGatewayStatus
    {
        return $this->sms->isAvailable()
            ? SmsGatewayStatus::fromMode($this->integrations->mode(IntegrationKind::Sms))
            : SmsGatewayStatus::Unavailable;
    }
}
