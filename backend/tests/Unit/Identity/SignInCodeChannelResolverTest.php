<?php

declare(strict_types=1);

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Identity\OtpChallenge;
use App\Enums\Identity\OtpChannel;
use App\Services\Identity\SignInCodeChannelResolver;
use App\Services\Identity\SignInCodeChannels;
use App\Services\Identity\SignInSmsCap;
use App\Support\ValueObjects\SignInIdentifier;
use Carbon\CarbonImmutable;

function smsAvailabilityOf(bool $available): SmsAvailability
{
    return new readonly class($available) implements SmsAvailability
    {
        public function __construct(private bool $available) {}

        public function isAvailable(): bool
        {
            return $this->available;
        }
    };
}

function channelResolver(bool $smsAvailable): SignInCodeChannelResolver
{
    app()->instance(SmsAvailability::class, smsAvailabilityOf($smsAvailable));

    return app(SignInCodeChannelResolver::class);
}

function useUpSignInSmsCap(): void
{
    $challenge = new OtpChallenge('6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e25', OtpChannel::Sms, 6, CarbonImmutable::now(), CarbonImmutable::now());

    foreach (range(1, config()->integer('moashir.auth.otp.sign_in_sms_per_hour')) as $sent) {
        app(SignInSmsCap::class)->recordSent($challenge);
    }
}

it('follows the typed identifier, the allowed channels, the gateway and the hourly cap', function (string $typed, array $allowed, bool $smsAvailable, bool $capReached, OtpChannel $expected): void {
    allowSignInCodeChannels(...$allowed);
    config()->set('moashir.auth.otp.sign_in_sms_per_hour', 2);

    if ($capReached) {
        useUpSignInSmsCap();
    }

    $identifier = SignInIdentifier::tryParse($typed);

    expect($identifier)->toBeInstanceOf(SignInIdentifier::class)
        ->and($identifier instanceof SignInIdentifier ? channelResolver($smsAvailable)->forIdentifier($identifier) : null)->toBe($expected);
})->with([
    'mobile, SMS allowed and available' => ['0551234567', ['email', 'sms'], true, false, OtpChannel::Sms],
    'mobile, e-mail only' => ['0551234567', ['email'], true, false, OtpChannel::Email],
    'mobile, gateway unavailable' => ['0551234567', ['email', 'sms'], false, false, OtpChannel::Email],
    'mobile, hourly cap reached' => ['0551234567', ['email', 'sms'], true, true, OtpChannel::Email],
    'e-mail, SMS allowed' => ['person@moashir.example.sa', ['email', 'sms'], true, false, OtpChannel::Email],
    'unified number, SMS allowed' => ['7012345678', ['email', 'sms'], true, false, OtpChannel::Email],
]);

it('keeps the previous channel on resend unless SMS can no longer be used', function (OtpChannel $previous, array $allowed, bool $smsAvailable, OtpChannel $expected): void {
    allowSignInCodeChannels(...$allowed);

    expect(channelResolver($smsAvailable)->forResend($previous))->toBe($expected);
})->with([
    'SMS still usable' => [OtpChannel::Sms, ['email', 'sms'], true, OtpChannel::Sms],
    'SMS no longer allowed' => [OtpChannel::Sms, ['email'], true, OtpChannel::Email],
    'gateway gone' => [OtpChannel::Sms, ['email', 'sms'], false, OtpChannel::Email],
    'e-mail stays e-mail' => [OtpChannel::Email, ['email', 'sms'], true, OtpChannel::Email],
]);

it('always treats e-mail as allowed, even if a stored value lacks it', function (): void {
    allowSignInCodeChannels('sms');

    expect(channelResolver(true)->forResend(OtpChannel::Sms))->toBe(OtpChannel::Sms)
        ->and(app(SignInCodeChannels::class)->allowed())->toBe([OtpChannel::Email, OtpChannel::Sms]);
});

it('counts only SMS challenges toward the hourly cap', function (): void {
    config()->set('moashir.auth.otp.sign_in_sms_per_hour', 1);
    $cap = app(SignInSmsCap::class);

    $cap->recordSent(new OtpChallenge('6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e26', OtpChannel::Email, 6, CarbonImmutable::now(), CarbonImmutable::now()));
    expect($cap->hasRoom())->toBeTrue();

    $cap->recordSent(new OtpChallenge('6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e27', OtpChannel::Sms, 6, CarbonImmutable::now(), CarbonImmutable::now()));
    expect($cap->hasRoom())->toBeFalse();
});
