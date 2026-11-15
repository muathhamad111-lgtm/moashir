<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\RoleKey;

final readonly class AuthSettingsUpdate
{
    /**
     * @param  list<OtpChannel>|null  $entitySignInCodeChannels
     * @param  list<RoleKey>|null  $twoFactorRoles
     */
    public function __construct(
        public ?array $entitySignInCodeChannels,
        public ?bool $generalEmailNotifications,
        public ?bool $entityRegistrationReview,
        public ?bool $twoFactorEnabled,
        public ?array $twoFactorRoles,
        public ?string $reason,
    ) {}

    public function isEmpty(): bool
    {
        return $this->entitySignInCodeChannels === null
            && $this->generalEmailNotifications === null
            && $this->entityRegistrationReview === null
            && $this->twoFactorEnabled === null
            && $this->twoFactorRoles === null;
    }

    /**
     * @return list<string>
     */
    public function channelValues(): array
    {
        return array_map(static fn (OtpChannel $channel): string => $channel->value, $this->entitySignInCodeChannels ?? []);
    }

    /**
     * @return list<string>
     */
    public function twoFactorRoleValues(): array
    {
        return array_map(static fn (RoleKey $role): string => $role->value, $this->twoFactorRoles ?? []);
    }
}
