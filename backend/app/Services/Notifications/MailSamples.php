<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Contact\ContactSenderNotice;
use App\Data\Entities\EntityNotice;
use App\Data\Mail\MailSample;
use App\Enums\Identity\Locale;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\UserStatus;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Contact\ContactAcknowledgementNotification;
use App\Notifications\Contact\ContactReplyNotification;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\AccountLockedNotification;
use App\Notifications\Identity\AccountStatusChangedNotification;
use App\Notifications\Identity\MfaResetNotification;
use App\Notifications\Identity\OtpCodeNotification;
use App\Notifications\Identity\PasswordResetLinkNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Notifications\Identity\StaffInvitationNotification;
use App\Notifications\Identity\SuperAdminEmailChangedNotification;
use App\Support\Mail\NotificationTexts;
use App\Support\Mail\SampleReplacements;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

final readonly class MailSamples
{
    public const array MAIN = [
        'REG-02.account',
        'USR-04',
        'USR-01',
        'REG-01.entities.entity',
        'PAY-06.payments.entity',
        'ACC-08.assessments.accredited.entity',
        'ACC-09.assessments.accredited.entity',
        'CNT-02.guest',
    ];

    private const string RECIPIENT = 'sara.alabdullah@example.sa';

    private const int RECIPIENT_ID = 9001;

    private const string OTP_CODE = '482915';

    private const string MASKED_NEW_ADDRESS = 'p•••••@moashir.example.sa';

    private const string SECURITY_MODULE = 'SEC';

    private const string LINK_TOKEN = 'preview-only-7f3c9a1e5b2d4c6e8a0f1b3d5e7c9a2b4d6f8e0a1c3e5b7d';

    private const string CONTACT_REFERENCE = 'CNT-2026-000045';

    private const string CONTACT_LINK = '/messages/2f6d9c1a-3b5e-4f7a-9c2d-6e8b0a1c3d5f';

    private const array RECIPIENT_NAME = [
        'ar' => 'سارة العبدالله',
        'en' => 'Sara Al-Abdullah',
    ];

    private const array CONTACT_REPLY = [
        'ar' => "شكرًا لتواصلكم مع منصة مؤشر.\n\nتستغرق مراجعة طلب التقييم المعتمد أربعة عشر يومًا من تاريخ إسناده إلى المقيّم، ويمكنكم متابعة حالته من صفحة تقييماتي.",
        'en' => "Thank you for contacting the Moashir platform.\n\nThe review of an accredited assessment request takes fourteen days from its assignment to an assessor. You can follow its status from the My assessments page.",
    ];

    private const array LINKS = [
        'REG' => '/profile',
        'SELF' => '/assessments',
        'ACC' => '/assessments',
        'PER' => '/paths/accredited',
        'ADV' => '/assessments',
        'EVD' => '/assessments',
        'CERT' => '/certificates',
        'PAY' => '/payments',
        'FIN' => '/finance/transactions',
        'CNT' => '/admin/contact-messages',
    ];

    public function __construct(private Repository $config) {}

    /**
     * @return list<MailSample>
     */
    public function main(Locale $locale): array
    {
        $samples = [];

        foreach (self::MAIN as $name) {
            $code = NotificationCode::from(explode('.', $name, 2)[0]);
            $samples = [...$samples, ...array_filter($this->forCode($code, $locale), static fn (MailSample $sample): bool => $sample->name === $name)];
        }

        return array_values($samples);
    }

    /**
     * @return list<MailSample>
     */
    public function forCode(NotificationCode $code, Locale $locale): array
    {
        return $this->dedicated($code, $locale) ?? array_map(
            fn (string $prefix): MailSample => $this->forText($code, $prefix, $locale),
            NotificationTexts::forCode($code),
        );
    }

    public function forText(NotificationCode $code, string $prefix, Locale $locale): MailSample
    {
        $replacements = SampleReplacements::for($prefix);
        $notice = new EntityNotice(
            $code,
            LocalizedText::fromTranslationWith($prefix.'.title', $replacements),
            LocalizedText::fromTranslationWith($prefix.'.body', $replacements),
            self::LINKS[$code->module()] ?? null,
            true,
            false,
        );
        $variant = array_values(array_diff(array_slice(explode('.', $prefix), 1), [$code->value]));

        return new MailSample(implode('.', [$code->value, ...$variant]), new EntityNotification($notice), $this->account($locale));
    }

    /**
     * @return list<MailSample>|null
     */
    private function dedicated(NotificationCode $code, Locale $locale): ?array
    {
        $account = $this->account($locale);

        return match ($code) {
            NotificationCode::StaffInvited => [new MailSample('USR-01', new StaffInvitationNotification(self::LINK_TOKEN, $this->config->integer('moashir.auth.invitation_ttl_hours')), $account)],
            NotificationCode::AccountLocked => [new MailSample('USR-02', new AccountLockedNotification(self::RECIPIENT_ID, $this->config->integer('moashir.auth.lockout.minutes')), $account)],
            NotificationCode::AccountStatusChanged => [
                new MailSample('USR-03.suspended', new AccountStatusChangedNotification(UserStatus::Suspended), $account),
                new MailSample('USR-03.active', new AccountStatusChangedNotification(UserStatus::Active), $account),
                new MailSample('USR-03.mfa_reset', new MfaResetNotification, $account),
            ],
            NotificationCode::PasswordResetRequested => [new MailSample('USR-04', new PasswordResetLinkNotification(self::LINK_TOKEN, $this->config->integer('auth.passwords.users.expire')), $account)],
            NotificationCode::SuperAdminEmailChanged => [
                new MailSample('USR-05.address', new SuperAdminEmailChangedNotification(self::MASKED_NEW_ADDRESS), $this->address()),
                new MailSample('USR-05.account', new SecurityAlertNotification($code), $account),
            ],
            NotificationCode::OtpCode => $this->otp($account),
            NotificationCode::ContactReplySent => $this->contact($code, $account, $locale),
            NotificationCode::ContactMessageAcknowledged => $this->contact($code, $account, $locale),
            default => $code->module() === self::SECURITY_MODULE ? $this->securityAlert($code, $account) : null,
        };
    }

    /**
     * @return list<MailSample>
     */
    private function otp(User $account): array
    {
        $minutes = intdiv($this->config->integer('moashir.auth.otp.expiry_seconds'), 60);

        return [
            new MailSample('REG-02.account', new OtpCodeNotification(self::OTP_CODE, OtpChannel::Email, $minutes), $account),
            new MailSample('REG-02.address', new OtpCodeNotification(self::OTP_CODE, OtpChannel::Email, $minutes), $this->address()),
        ];
    }

    /**
     * @return list<MailSample>
     */
    private function securityAlert(NotificationCode $code, User $account): array
    {
        return [
            new MailSample($code->value.'.account', new SecurityAlertNotification($code), $account),
            new MailSample($code->value.'.mailbox', new SecurityAlertNotification($code), $this->address()),
        ];
    }

    /**
     * @return list<MailSample>
     */
    private function contact(NotificationCode $code, User $account, Locale $locale): array
    {
        return [
            new MailSample($code->value.'.account', $this->contactNotification($code, self::CONTACT_LINK, $locale), $account),
            new MailSample($code->value.'.guest', $this->contactNotification($code, null, $locale), $this->address()),
        ];
    }

    private function contactNotification(NotificationCode $code, ?string $link, Locale $locale): Notification
    {
        $enquiry = LocalizedText::fromTranslation('contact.enquiry_types.general');

        return $code === NotificationCode::ContactReplySent
            ? new ContactReplyNotification(new ContactSenderNotice(self::CONTACT_REFERENCE, $enquiry, $link, self::CONTACT_REPLY[$locale->value]))
            : new ContactAcknowledgementNotification(new ContactSenderNotice(self::CONTACT_REFERENCE, $enquiry, $link));
    }

    private function account(Locale $locale): User
    {
        return new User()->forceFill([
            'id' => self::RECIPIENT_ID,
            'name' => self::RECIPIENT_NAME[$locale->value],
            'email' => self::RECIPIENT,
            'locale' => $locale,
        ]);
    }

    private function address(): AnonymousNotifiable
    {
        return new AnonymousNotifiable()->route('mail', self::RECIPIENT);
    }
}
