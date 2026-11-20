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
     * @param  list<RoleKey>  $nationalIdRoles
     * @param  list<RoleKey>  $fileAccessRoles
     */
    public function __construct(
        public array $entitySignInCodeChannels,
        public bool $generalEmailNotifications,
        public bool $entityRegistrationReview,
        public bool $twoFactorEnabled,
        public array $twoFactorRoles,
        public SmsGatewayStatus $smsStatus,
        public bool $nationalIdEnabled,
        public array $nationalIdRoles,
        public array $fileAccessRoles,
    ) {}
}
