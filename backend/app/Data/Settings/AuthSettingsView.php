<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\SmsGatewayStatus;

final readonly class AuthSettingsView
{
    /**
     * @param  list<OtpChannel>  $entitySignInCodeChannels
     * @param  list<RoleKey>  $twoFactorRoles
     */
    public function __construct(
        public array $entitySignInCodeChannels,
        public bool $generalEmailNotifications,
        public bool $entityRegistrationReview,
        public bool $twoFactorEnabled,
        public array $twoFactorRoles,
        public SmsGatewayStatus $smsStatus,
    ) {}
}
