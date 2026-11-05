<?php

declare(strict_types=1);

use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Models\Settings\Setting;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

function signInWith(string $identifier, string $password): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['identifier' => $identifier, 'password' => $password]);
}

function requestSignInCode(string $identifier): TestResponse
{
    return test()->postJson('/api/v1/auth/otp/request', ['identifier' => $identifier]);
}

function allowSignInCodeChannels(string ...$channels): void
{
    Setting::query()->updateOrCreate(
        ['key' => SettingKey::EntitySignInCodeChannels->value],
        ['group' => SettingKey::EntitySignInCodeChannels->group(), 'type' => SettingKey::EntitySignInCodeChannels->type()],
    )->forceFill(['value' => array_values($channels)])->save();
}

function verifiedMobileOwner(string $phone = '+966551234567'): User
{
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['phone' => $phone])->save();
    $owner->forceFill(['phone_verified_at' => Date::now()])->save();

    return $owner->refresh();
}

function lastOtpCodeSentTo(User $user): string
{
    $code = '';

    Notification::assertSentTo($user, OtpCodeNotification::class, static function (OtpCodeNotification $notification) use ($user, &$code): bool {
        $code = (string) $notification->toMail($user)->code;

        return true;
    });

    return $code;
}

function smsCodeSentTo(User $user): string
{
    $code = '';

    Notification::assertSentTo($user, OtpCodeNotification::class, static function (OtpCodeNotification $notification, array $channels) use ($user, &$code): bool {
        $code = (string) $notification->toMail($user)->code;

        return $channels === [SmsChannel::class];
    });

    return $code;
}

function wrongCodeFor(string $code): string
{
    return $code === '000000' ? '111111' : '000000';
}
