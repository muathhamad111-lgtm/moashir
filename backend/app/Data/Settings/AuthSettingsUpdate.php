<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Identity\OtpChannel;

final readonly class AuthSettingsUpdate
{
    /**
     * @param  list<OtpChannel>|null  $entitySignInCodeChannels
     */
    public function __construct(public ?array $entitySignInCodeChannels, public ?string $reason) {}

    public function isEmpty(): bool
    {
        return $this->entitySignInCodeChannels === null;
    }

    /**
     * @return list<string>
     */
    public function channelValues(): array
    {
        return array_map(static fn (OtpChannel $channel): string => $channel->value, $this->entitySignInCodeChannels ?? []);
    }
}
