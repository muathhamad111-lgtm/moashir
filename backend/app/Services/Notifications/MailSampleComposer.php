<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Mail\MailSample;
use App\Enums\Identity\Locale;
use App\Exceptions\Console\MailSampleNotRendered;
use Illuminate\Mail\MailManager;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Traits\Localizable;
use Symfony\Component\Mime\Email;

/**
 * Builds a sample e-mail through the real mail channel on the `array` mailer, so nothing leaves the server here.
 */
final readonly class MailSampleComposer
{
    use Localizable;

    private const string MAILER = 'array';

    public function __construct(private MailManager $mail, private MailChannel $channel) {}

    public function compose(MailSample $sample, Locale $locale): Email
    {
        return $this->withLocale($locale->value, fn (): Email => $this->captured($sample));
    }

    private function captured(MailSample $sample): Email
    {
        $previous = $this->mail->getDefaultDriver();
        $this->mail->setDefaultDriver(self::MAILER);

        try {
            $sent = $this->channel->send($sample->notifiable, $sample->notification);
        } finally {
            $this->mail->setDefaultDriver($previous);
        }

        $email = $sent?->getSymfonySentMessage()->getOriginalMessage();

        return $email instanceof Email ? $email : throw MailSampleNotRendered::named($sample->name);
    }
}
