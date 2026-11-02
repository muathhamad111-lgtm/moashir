<?php

declare(strict_types=1);

use App\Contracts\SmsGateway;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;
use App\Enums\Identity\OtpChannel;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Integrations\SmsPurpose;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\SmsPermanentFailure;
use App\Exceptions\Integrations\SmsTransientFailure;
use App\Models\Identity\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Integrations\IntegrationNotifier;
use App\Services\Integrations\SettingsSmsGateway;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function otpSms(): SmsMessage
{
    return new SmsMessage(new PhoneNumber('+966551234567'), 'رمز التحقق في منصة مؤشر هو 482915', SmsPurpose::Otp);
}

function smsFailureOf(callable $send): ?Throwable
{
    try {
        $send();
    } catch (Throwable $failure) {
        return $failure;
    }

    return null;
}

it('sends through Taqnyat with the international number and returns the message id', function (): void {
    configureTaqnyat(['is_active' => true]);
    fakeProviderRoutes(['POST api.taqnyat.sa/v1/messages' => Http::response(['statusCode' => 201, 'messageId' => '5829452722', 'msgLength' => 1, 'accepted' => '[966551234567,]', 'rejected' => '[]'], 201)]);

    $result = app(SettingsSmsGateway::class)->send(otpSms());

    expect($result->provider)->toBe(IntegrationProvider::Taqnyat)
        ->and($result->providerMessageId)->toBe('5829452722')
        ->and($result->segments)->toBe(1);
    Http::assertSent(static fn (ClientRequest $request): bool => $request['recipients'] === ['966551234567'] && $request['sender'] === 'Moashir');
});

it('treats a Taqnyat recipient listed as rejected as a permanent failure', function (): void {
    configureTaqnyat(['is_active' => true]);
    fakeProviderRoutes(['POST api.taqnyat.sa/v1/messages' => Http::response(['statusCode' => 201, 'messageId' => '1', 'accepted' => '[]', 'rejected' => '[966551234567,]'], 201)]);

    $failure = smsFailureOf(fn () => app(SettingsSmsGateway::class)->send(otpSms()));

    expect($failure)->toBeInstanceOf(SmsPermanentFailure::class)
        ->and($failure?->kind)->toBe(ProviderErrorKind::RecipientInvalid);
});

it('maps the documented Taqnyat errors', function (int $status, string $message, string $class, ProviderErrorKind $kind): void {
    configureTaqnyat(['is_active' => true]);
    fakeProviderRoutes(['POST api.taqnyat.sa/v1/messages' => Http::response(['statusCode' => $status, 'message' => $message], $status)]);

    $failure = smsFailureOf(fn () => app(SettingsSmsGateway::class)->send(otpSms()));

    expect($failure)->toBeInstanceOf($class)->and($failure?->kind)->toBe($kind);
})->with([
    [400, 'Your balance is 0', SmsPermanentFailure::class, ProviderErrorKind::QuotaExhausted],
    [400, 'Sender Name not active .', SmsPermanentFailure::class, ProviderErrorKind::Misconfigured],
    [400, 'mobile number is incorrect', SmsPermanentFailure::class, ProviderErrorKind::RecipientInvalid],
    [400, 'this ip Not authorized', SmsPermanentFailure::class, ProviderErrorKind::Misconfigured],
    [400, 'SMS-API not responding', SmsTransientFailure::class, ProviderErrorKind::Unavailable],
    [400, 'Sending SMS stopped from support', SmsPermanentFailure::class, ProviderErrorKind::Rejected],
    [401, 'invalid credentials information.', SmsPermanentFailure::class, ProviderErrorKind::AuthFailed],
    [500, 'Server error', SmsTransientFailure::class, ProviderErrorKind::Unavailable],
]);

it('sends through Msegat with a JSON body and reads the bulk id', function (): void {
    configureMsegat([], ['is_active' => true]);
    fakeProviderRoutes(['POST www.msegat.com/gw/sendsms.php' => Http::response(['code' => '1', 'message' => 'Success', 'BulkId' => '8812'])]);

    $result = app(SettingsSmsGateway::class)->send(otpSms());

    expect($result->provider)->toBe(IntegrationProvider::Msegat)->and($result->providerMessageId)->toBe('8812');
    Http::assertSent(static fn (ClientRequest $request): bool => $request->isJson() && $request['numbers'] === '966551234567'
        && $request['userSender'] === 'Moashir' && $request['msgEncoding'] === 'UTF8' && $request['apiKey'] === FAKE_MSEGAT_KEY);
});

it('maps the Msegat response codes', function (string $code, string $class, ProviderErrorKind $kind): void {
    configureMsegat([], ['is_active' => true]);
    fakeProviderRoutes(['POST www.msegat.com/gw/sendsms.php' => Http::response(['code' => $code, 'message' => 'Failure'])]);

    $failure = smsFailureOf(fn () => app(SettingsSmsGateway::class)->send(otpSms()));

    expect($failure)->toBeInstanceOf($class)->and($failure?->kind)->toBe($kind);
})->with([
    ['1020', SmsPermanentFailure::class, ProviderErrorKind::AuthFailed],
    ['M0002', SmsPermanentFailure::class, ProviderErrorKind::AuthFailed],
    ['1060', SmsPermanentFailure::class, ProviderErrorKind::QuotaExhausted],
    ['1110', SmsPermanentFailure::class, ProviderErrorKind::Misconfigured],
    ['M0037', SmsPermanentFailure::class, ProviderErrorKind::Misconfigured],
    ['M0090', SmsPermanentFailure::class, ProviderErrorKind::Misconfigured],
    ['1120', SmsPermanentFailure::class, ProviderErrorKind::RecipientInvalid],
    ['1061', SmsPermanentFailure::class, ProviderErrorKind::Rejected],
    ['M9999', SmsPermanentFailure::class, ProviderErrorKind::Unknown],
]);

it('treats an unconfigured SMS kind as a permanent failure without any call', function (): void {
    expect(smsFailureOf(fn () => app(SettingsSmsGateway::class)->send(otpSms())))->toBeInstanceOf(SmsPermanentFailure::class);

    Http::assertNothingSent();
});

it('falls back to e-mail and raises INT-01 once when the SMS of a code fails for good', function (): void {
    Notification::fake();
    $admin = systemAdmin();
    $user = User::factory()->create(['phone' => '+966551234567']);
    $gateway = new class implements SmsGateway
    {
        public function send(SmsMessage $message): SmsSendResult
        {
            throw new SmsPermanentFailure(ProviderErrorKind::Misconfigured, IntegrationProvider::Taqnyat);
        }
    };
    $channel = new SmsChannel($gateway, app(IntegrationNotifier::class));
    $notification = new OtpCodeNotification('482915', OtpChannel::Sms, 5);

    $channel->send($user, $notification);
    $channel->send($user, $notification);

    Notification::assertSentToTimes($user, OtpCodeNotification::class, 2);
    Notification::assertSentTo($user, OtpCodeNotification::class, static fn (OtpCodeNotification $sent, array $channels): bool => $channels === ['mail']);
    Notification::assertSentToTimes($admin, EntityNotification::class, 1);
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $sent): bool => $sent->notice->code === NotificationCode::SmsSendFailed);
});

it('lets a transient SMS failure reach the queue for a retry', function (): void {
    $gateway = new class implements SmsGateway
    {
        public function send(SmsMessage $message): SmsSendResult
        {
            throw new SmsTransientFailure(ProviderErrorKind::Unavailable, IntegrationProvider::Msegat);
        }
    };
    $channel = new SmsChannel($gateway, app(IntegrationNotifier::class));

    expect(fn () => $channel->send(User::factory()->create(['phone' => '+966551234567']), new OtpCodeNotification('482915', OtpChannel::Sms, 5)))
        ->toThrow(SmsTransientFailure::class);
});

it('stops retrying a code after it expires and encrypts its queue payload', function (): void {
    $notification = new OtpCodeNotification('482915', OtpChannel::Sms, 5);

    expect($notification)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($notification->retryUntil()->getTimestamp())->toBeLessThanOrEqual(now()->addMinutes(5)->getTimestamp());
});
