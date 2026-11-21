<?php

declare(strict_types=1);

use App\Enums\Identity\OtpChannel;
use App\Enums\Integrations\SmsTemplateKey;
use App\Models\Identity\User;
use App\Notifications\Identity\OtpCodeNotification;
use App\Support\Integrations\SmsTemplateRules;
use App\Support\ValueObjects\LocalizedText;

it('uses the full Arabic plural forms for minutes', function (int $count, string $arabic, string $english): void {
    expect(trans_choice('quantities.minutes', $count, [], 'ar'))->toBe($arabic)
        ->and(trans_choice('quantities.minutes', $count, [], 'en'))->toBe($english);
})->with([
    'one' => [1, 'دقيقة واحدة', '1 minute'],
    'two' => [2, 'دقيقتين', '2 minutes'],
    'three' => [3, '3 دقائق', '3 minutes'],
    'five' => [5, '5 دقائق', '5 minutes'],
    'eleven' => [11, '11 دقيقة', '11 minutes'],
    'one hundred' => [100, '100 دقيقة', '100 minutes'],
]);

it('agrees the counted noun with the number for hours, files and entities', function (string $key, array $arabic): void {
    $forms = array_map(static fn (int $count): string => trans_choice('quantities.'.$key, $count, [], 'ar'), [1, 2, 3, 5, 11, 100]);

    expect($forms)->toBe($arabic);
})->with([
    'hours' => ['hours', ['ساعة واحدة', 'ساعتين', '3 ساعات', '5 ساعات', '11 ساعة', '100 ساعة']],
    'files' => ['files', ['ملف واحد', 'ملفين', '3 ملفات', '5 ملفات', '11 ملفًا', '100 ملف']],
    'entities' => ['entities', ['جهة واحدة', 'جهتين', '3 جهات', '5 جهات', '11 جهة', '100 جهة']],
    'winners' => ['winners', ['فائز واحد', 'فائزين اثنين', '3 فائزين', '5 فائزين', '11 فائزًا', '100 فائز']],
]);

it('defines six Arabic forms and two English forms for every quantity', function (): void {
    $arabic = trans('quantities', [], 'ar');
    $english = trans('quantities', [], 'en');

    expect($arabic)->toBeArray()->and(array_keys((array) $arabic))->toBe(array_keys((array) $english));

    foreach ((array) $arabic as $key => $forms) {
        expect(explode('|', (string) $forms))->toHaveCount(6, 'quantities.'.$key)
            ->and(explode('|', (string) ((array) $english)[$key]))->toHaveCount(2, 'quantities.'.$key);
    }
});

it('builds a localized quantity for notification texts', function (): void {
    expect(LocalizedText::fromChoice('quantities.files', 2)->toArray())->toBe(['ar' => 'ملفين', 'en' => '2 files']);
});

it('leaves no number next to a noun without a plural form in the notification texts', function (): void {
    $pattern = '/:[a-z_]+ (دقيقة|دقائق|ساعة|ساعات|يوم|أيام|ملف|ملفات|جهات|محاولات|فائزين|تقييمات|عمليات|طلبات)/u';

    foreach (['notifications', 'integrations', 'mail'] as $file) {
        $source = (string) file_get_contents(lang_path('ar/'.$file.'.php'));

        expect(preg_match($pattern, $source))->toBe(0, 'lang/ar/'.$file.'.php');
    }
});

it('sends the default SMS code text with the correct plural', function (int $minutes, string $arabic, string $english): void {
    $notification = new OtpCodeNotification('482915', OtpChannel::Sms, $minutes);
    $user = new User;

    app()->setLocale('ar');
    expect($notification->toSms($user))->toBe('رمز التحقق في منصة مؤشر هو 482915 وهو صالح لمدة '.$arabic.'.');

    app()->setLocale('en');
    expect($notification->toSms($user))->toBe('Your Moashir verification code is 482915. It is valid for '.$english.'.');
})->with([
    'one minute' => [1, 'دقيقة واحدة', '1 minute'],
    'two minutes' => [2, 'دقيقتين', '2 minutes'],
    'five minutes' => [5, '5 دقائق', '5 minutes'],
    'fifteen minutes' => [15, '15 دقيقة', '15 minutes'],
]);

it('checks the SMS length with the longest validity phrase of each language', function (): void {
    expect(SmsTemplateKey::OtpCode->longestSample('ar')['validity'])->toBe('دقيقة واحدة')
        ->and(SmsTemplateKey::OtpCode->longestSample('en')['validity'])->toBe('15 minutes')
        ->and(SmsTemplateRules::errorFor(SmsTemplateKey::OtpCode, (string) trans('integrations.sms_templates.otp_code.default_body', [], 'ar'), 'ar'))->toBeNull()
        ->and(SmsTemplateRules::errorFor(SmsTemplateKey::OtpCode, (string) trans('integrations.sms_templates.otp_code.default_body', [], 'en'), 'en'))->toBeNull()
        ->and(SmsTemplateRules::errorFor(SmsTemplateKey::OtpCode, 'رمزك {code} صالح '.str_repeat('لمدة ', 9).'{validity}', 'ar'))->toBe('too_long');
});
