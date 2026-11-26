<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Integrations\SmsAvailability;
use App\Enums\Identity\OtpChannel;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\StringList;

final readonly class SignInCodeChannels
{
    public function __construct(private SettingsStore $store, private SmsAvailability $sms) {}

    /**
     * @return list<OtpChannel>
     */
    public function allowed(): array
    {
        $stored = StringList::from($this->store->get(SettingKey::EntitySignInCodeChannels));

        return array_values(array_filter(
            OtpChannel::cases(),
            static fn (OtpChannel $channel): bool => $channel === OtpChannel::Email || in_array($channel->value, $stored, true),
        ));
    }

    /**
     * @return list<OtpChannel>
     */
    public function usable(): array
    {
        return $this->canSendSms() ? [OtpChannel::Email, OtpChannel::Sms] : [OtpChannel::Email];
    }

    public function canSendSms(): bool
    {
        return in_array(OtpChannel::Sms, $this->allowed(), true) && $this->sms->isAvailable();
    }
}
