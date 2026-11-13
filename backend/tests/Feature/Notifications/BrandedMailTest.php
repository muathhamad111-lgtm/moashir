<?php

declare(strict_types=1);

use App\Data\Mail\MailSample;
use App\Data\Mail\RenderedMail;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountLockedNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Services\Notifications\MailPreviewRenderer;
use App\Services\Notifications\MailSamples;
use App\Support\Mail\NotificationTexts;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;

function brandedSample(string $name, Locale $locale): RenderedMail
{
    $code = NotificationCode::from(explode('.', $name, 2)[0]);
    $samples = array_values(array_filter(app(MailSamples::class)->forCode($code, $locale), static fn (MailSample $sample): bool => $sample->name === $name));

    return app(MailPreviewRenderer::class)->render($samples[0], $locale);
}

function lastSentEmail(): Email
{
    $transport = app('mailer')->getSymfonyTransport();
    assert($transport instanceof ArrayTransport);
    $email = $transport->messages()->last()?->getOriginalMessage();
    assert($email instanceof Email);

    return $email;
}

function assertBrandedFrame(RenderedMail $mail, Locale $locale): void
{
    $isArabic = $locale === Locale::Arabic;
    $frontend = rtrim(config()->string('moashir.frontend_url'), '/');

    expect($mail->html)->toContain('<html lang="'.$locale->value.'" dir="'.($isArabic ? 'rtl' : 'ltr').'">')
        ->and($mail->html)->toContain('class="mail-body mail-'.($isArabic ? 'rtl' : 'ltr').'"')
        ->and($mail->html)->toContain('text-align: '.($isArabic ? 'right' : 'left'))
        ->and($mail->html)->not->toContain('text-align: '.($isArabic ? 'left' : 'right'))
        ->and($mail->html)->toContain(e(__('mail.copyright', ['year' => now()->year], $locale->value)))
        ->and($mail->html)->toContain('href="'.$frontend.'/privacy"')
        ->and($mail->html)->toContain('href="'.$frontend.'/terms"')
        ->and($mail->html)->toMatch('/<img class="mail-logo" src="cid:[^"]+"/')
        ->and($mail->subject)->toStartWith(__('mail.brand', [], $locale->value).' — ')
        ->and($mail->text)->toContain((string) __('mail.sign_off', [], $locale->value))
        ->and($mail->text)->toContain((string) __('mail.team', [], $locale->value))
        ->and($mail->text)->toContain($frontend.'/privacy')
        ->and($mail->inlineImages)->toBe(1);

    expect($mail->html.$mail->text)->not->toContain('Hello!')
        ->and($mail->html.$mail->text)->not->toContain($isArabic ? 'Regards' : 'مع التحية')
        ->and($mail->html.$mail->text)->not->toContain($isArabic ? 'All rights reserved' : 'جميع الحقوق محفوظة')
        ->and($mail->html)->not->toContain('<script')
        ->and($mail->html)->not->toContain('<link')
        ->and($mail->html)->not->toContain('url(')
        ->and($mail->html)->not->toContain('@import')
        ->and($mail->html.$mail->text)->not->toMatch('/(?<![\w\/]):[a-z_]{3,}\b/');

    preg_match_all('/(?:href|src)="([^"]+)"/', $mail->html, $references);

    foreach ($references[1] as $reference) {
        expect(str_starts_with($reference, $frontend.'/') || str_starts_with($reference, 'cid:'))->toBeTrue('External reference: '.$reference);
    }

    preg_match_all('#https?://[^\s"<>]+#', $mail->html.' '.$mail->text, $urls);

    foreach ($urls[0] as $url) {
        expect($url)->toStartWith($frontend.'/');
    }
}

it('renders the Arabic e-mail right to left with the Arabic greeting, sign-off and footer', function (): void {
    $mail = brandedSample('REG-02.account', Locale::Arabic);

    assertBrandedFrame($mail, Locale::Arabic);
    expect($mail->subject)->toBe('منصة مؤشر — رمز التحقق')
        ->and($mail->html)->toContain('مرحباً سارة العبدالله،')
        ->and($mail->html)->toContain('مع التحية،<br>فريق منصة مؤشر')
        ->and($mail->html)->toContain('© '.now()->year.' منصة مؤشر. جميع الحقوق محفوظة.')
        ->and($mail->html)->toContain('تصلك هذه الرسالة لأن لديك حسابًا في منصة مؤشر.')
        ->and($mail->text)->toContain('مرحباً سارة العبدالله،');
});

it('renders the English e-mail left to right with the English frame', function (): void {
    $mail = brandedSample('REG-02.account', Locale::English);

    assertBrandedFrame($mail, Locale::English);
    expect($mail->subject)->toBe('Moashir — Your verification code')
        ->and($mail->html)->toContain('Hello Sara Al-Abdullah,')
        ->and($mail->html)->toContain('Regards,<br>The Moashir team')
        ->and($mail->html)->toContain('© '.now()->year.' Moashir. All rights reserved.');
});

it('greets a recipient without an account in a generic way and explains why the e-mail was sent', function (Locale $locale, string $greeting, string $reason): void {
    $mail = brandedSample('REG-02.address', $locale);

    expect($mail->html)->toContain($greeting)->and($mail->html)->toContain($reason);
})->with([
    'Arabic' => [Locale::Arabic, 'السلام عليكم،', 'تصلك هذه الرسالة لأن عنوان البريد هذا أُدخل في منصة مؤشر لتأكيده.'],
    'English' => [Locale::English, 'Hello,', 'You are receiving this e-mail because this address was entered on the Moashir platform for confirmation.'],
]);

it('shows the code large, isolated left to right and selectable, with the validity plural and the safety lines', function (): void {
    $arabic = brandedSample('REG-02.account', Locale::Arabic);
    $english = brandedSample('REG-02.account', Locale::English);

    expect($arabic->html)->toMatch('#<td class="mail-code-box" align="center" dir="ltr"[^>]*><bdi class="mail-code" dir="ltr"[^>]*font-size: 34px[^>]*>482915</bdi>#')
        ->and($arabic->html)->not->toContain('user-select')
        ->and($arabic->html)->toContain('الرمز صالح لمدة 5 دقائق.')
        ->and($arabic->html)->toContain('لا تشارك هذا الرمز مع أي شخص')
        ->and($arabic->html)->toContain('إذا لم تطلب هذا الرمز فيمكنك تجاهل هذه الرسالة.')
        ->and($arabic->text)->toContain('الرمز: 482915')
        ->and($english->html)->toContain('The code is valid for 5 minutes.')
        ->and($english->text)->toContain('Code: 482915');
});

it('shows the reset and invitation buttons with the raw link below them and the expiry plural', function (): void {
    config(['moashir.auth.invitation_ttl_hours' => 2]);
    $reset = brandedSample('USR-04', Locale::Arabic);
    $invitation = brandedSample('USR-01', Locale::Arabic);
    $resetUrl = config()->string('moashir.frontend_url').'/auth/reset-password/';

    expect($reset->html)->toMatch('#<a class="mail-button-link" href="'.preg_quote($resetUrl, '#').'[^"]+"[^>]*>إعادة تعيين كلمة المرور</a>#')
        ->and($reset->html)->toMatch('#<p class="mail-link" dir="ltr"[^>]*><a class="mail-link-anchor" href="'.preg_quote($resetUrl, '#').'[^"]+"[^>]*>'.preg_quote($resetUrl, '#').'#')
        ->and($reset->html)->toContain('word-break: break-all')
        ->and($reset->html)->toContain('هذا الرابط صالح لمدة 60 دقيقة ويُستخدم مرة واحدة.')
        ->and($reset->text)->toContain('إعادة تعيين كلمة المرور: '.$resetUrl)
        ->and($invitation->html)->toContain('هذه الدعوة صالحة لمدة ساعتين وتُستخدم مرة واحدة.')
        ->and($invitation->subject)->toBe('منصة مؤشر — دعوة للانضمام إلى فريق المنصة');
});

it('renders the staff reply as a quoted block before the follow-up line', function (): void {
    $mail = brandedSample('CNT-02.guest', Locale::Arabic);

    expect($mail->html)->toMatch('/<p class="mail-quote" dir="auto"[^>]*>شكرًا لتواصلكم مع منصة مؤشر.<\/p>/')
        ->and(strpos($mail->html, 'class="mail-quote"'))->toBeLessThan((int) strpos($mail->html, 'للمتابعة أرسل رسالة جديدة'))
        ->and($mail->html)->toContain('تصلك هذه الرسالة لأنك راسلت منصة مؤشر عبر نموذج التواصل.');
});

it('renders every notification code in Arabic and English through the branded layout', function (NotificationCode $code): void {
    foreach (Locale::cases() as $locale) {
        $samples = app(MailSamples::class)->forCode($code, $locale);

        expect($samples)->not->toBeEmpty();

        foreach ($samples as $sample) {
            $mail = app(MailPreviewRenderer::class)->render($sample, $locale);

            assertBrandedFrame($mail, $locale);
            expect(trim($mail->text))->not->toBe('');
        }
    }
})->with(NotificationCode::cases());

it('renders every notification text of the catalogue in both languages', function (): void {
    $samples = app(MailSamples::class);
    $renderer = app(MailPreviewRenderer::class);

    foreach (NotificationTexts::all() as $prefix) {
        foreach (Locale::cases() as $locale) {
            assertBrandedFrame($renderer->render($samples->forText(NotificationCode::RequestProcessingFailed, $prefix, $locale), $locale), $locale);
        }
    }
});

it('sends the e-mail in the preferred language of the recipient', function (string $locale, string $subject): void {
    $user = User::factory()->create(['locale' => $locale, 'name' => 'Sara Al-Abdullah']);

    $user->notifyNow(new AccountLockedNotification($user->id, 1), ['mail']);
    $email = lastSentEmail();

    expect((string) $email->getHtmlBody())->toContain('<html lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">')
        ->and($email->getSubject())->toBe($subject)
        ->and((string) $email->getTextBody())->toContain($locale === 'ar' ? 'لمدة دقيقة واحدة' : 'for 1 minute')
        ->and($email->getAttachments())->toHaveCount(1);
})->with([
    'Arabic' => ['ar', 'منصة مؤشر — تم قفل حسابك مؤقتًا'],
    'English' => ['en', 'Moashir — Your account has been temporarily locked'],
]);

it('falls back to Arabic for a mailbox without a stored language', function (): void {
    Notification::route('mail', 'security.alerts@example.sa')->notifyNow(new SecurityAlertNotification(NotificationCode::SuperAdminLogin));

    expect((string) lastSentEmail()->getHtmlBody())->toContain('<html lang="ar" dir="rtl">')
        ->and(lastSentEmail()->getSubject())->toBe('منصة مؤشر — دخول مدير النظام الرئيسي');
});

it('keeps every outgoing e-mail multipart with an HTML and a plain-text part', function (): void {
    $user = User::factory()->create(['locale' => 'ar']);

    $user->notifyNow(new AccountLockedNotification($user->id, 15), ['mail']);
    $email = lastSentEmail();

    expect($email->getHtmlBody())->toBeString()->not->toBe('')
        ->and($email->getTextBody())->toBeString()->toContain('تم قفل حسابك لمدة 15 دقيقة')
        ->and((string) $email->getTextBody())->not->toContain('<');
});
