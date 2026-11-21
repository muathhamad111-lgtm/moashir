<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Audit\AuditEntry;
use App\Data\Mail\ComposedMailSample;
use App\Data\Mail\MailSampleRun;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Console\MailSamplesRefused;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Audit\AuditRecorder;
use App\Services\Notifications\MailSampleComposer;
use App\Services\Notifications\MailSampleDeliveryGuard;
use App\Services\Notifications\MailSamples;
use App\Support\Integrations\SmtpFailure;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Traits\Localizable;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

final readonly class SendMailSamples
{
    use Localizable;

    private const string MAILER = 'settings';

    public function __construct(
        private MailSampleDeliveryGuard $guard,
        private MailSamples $samples,
        private MailSampleComposer $composer,
        private MailManager $mail,
        private AuditRecorder $audit,
    ) {}

    /**
     * Sends every sample of the run to each recipient as its own message and returns how many were sent.
     */
    public function handle(MailSampleRun $run): int
    {
        $connection = $this->guard->connection();
        $samples = $this->composed($run);
        $labels = array_map(static fn (ComposedMailSample $sample): string => $sample->label(), $samples);
        $sent = 0;

        try {
            foreach ($samples as $sample) {
                foreach ($run->recipients as $recipient) {
                    $this->deliver($sample, $recipient);
                    $sent++;
                }
            }
        } catch (TransportExceptionInterface $exception) {
            $kind = SmtpFailure::kindOf($exception);
            $this->record($connection, $run, $labels, $sent, $kind);

            throw MailSamplesRefused::deliveryFailed($kind, $sent);
        }

        $this->record($connection, $run, $labels, $sent, null);

        return $sent;
    }

    /**
     * @return list<ComposedMailSample>
     */
    private function composed(MailSampleRun $run): array
    {
        $composed = [];

        foreach ($run->locales as $locale) {
            $samples = $run->code instanceof NotificationCode ? $this->samples->forCode($run->code, $locale) : $this->samples->main($locale);

            foreach ($samples as $sample) {
                $composed[] = new ComposedMailSample($sample->name, $locale, $this->composer->compose($sample, $locale));
            }
        }

        return $composed;
    }

    /**
     * The SMTP settings pick the sender name by the current language, as for a real notification.
     */
    private function deliver(ComposedMailSample $sample, EmailAddress $recipient): void
    {
        $this->withLocale($sample->locale->value, fn (): mixed => $this->mail->mailer(self::MAILER)->getSymfonyTransport()->send($this->addressed($sample->email, $recipient)));
    }

    private function addressed(Email $email, EmailAddress $recipient): Email
    {
        $copy = clone $email;
        $copy->getHeaders()->remove('Cc');
        $copy->getHeaders()->remove('Bcc');
        $copy->getHeaders()->remove('Message-ID');

        return $copy->to($recipient->value);
    }

    /**
     * @param  list<string>  $samples
     */
    private function record(IntegrationCredential $connection, MailSampleRun $run, array $samples, int $sent, ?ProviderErrorKind $failure): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::MailSamplesSent, $connection, [], [
            'samples' => $samples,
            'sent' => $sent,
            'recipient_domains' => $run->recipientDomains(),
            'ok' => ! $failure instanceof ProviderErrorKind,
            'error_kind' => $failure?->value,
        ]));
    }
}
