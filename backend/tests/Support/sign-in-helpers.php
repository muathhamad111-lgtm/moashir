<?php

declare(strict_types=1);

use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Models\Settings\Setting;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

function signInWith(string $identifier, string $password): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['identifier' => $identifier, 'password' => $password]);
}

function setEntitySecondFactor(bool $enabled): void
{
    Setting::query()->updateOrCreate(
        ['key' => SettingKey::EntitySecondFactorEnabled->value],
        ['group' => SettingKey::EntitySecondFactorEnabled->group(), 'type' => SettingKey::EntitySecondFactorEnabled->type()],
    )->forceFill(['value' => $enabled])->save();
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

function wrongCodeFor(string $code): string
{
    return $code === '000000' ? '111111' : '000000';
}
