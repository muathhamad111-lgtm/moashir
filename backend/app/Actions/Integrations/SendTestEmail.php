<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Contracts\Integrations\SmtpTransportFactory;
use App\Data\Audit\AuditEntry;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Integrations\IntegrationProbe;
use App\Services\Integrations\OutgoingMailPreparer;
use App\Support\Integrations\SmtpFailure;
use App\Support\ValueObjects\EmailAddress;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

final readonly class SendTestEmail
{
    public function __construct(
        private IntegrationProbe $probe,
        private SmtpTransportFactory $transports,
        private OutgoingMailPreparer $preparer,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, EmailAddress $recipient): bool
    {
        $credentials = $this->probe->configured(IntegrationProvider::Smtp);

        try {
            $this->transports->build($credentials)->send($this->preparer->prepare($this->message($recipient), $credentials));
        } catch (ProviderCallFailed $failure) {
            $this->fail($actor, $credentials, $recipient, $failure->kind);
        } catch (TransportExceptionInterface $exception) {
            $this->fail($actor, $credentials, $recipient, SmtpFailure::kindOf($exception));
        }

        $this->record($actor, $credentials, $recipient, null);

        return $credentials->mode->isTest();
    }

    private function message(EmailAddress $recipient): Email
    {
        return (new Email)
            ->to($recipient->value)
            ->subject(__('integrations.email_test.subject', [], 'ar').' | '.__('integrations.email_test.subject', [], 'en'))
            ->text(__('integrations.email_test.body', [], 'ar')."\n\n".__('integrations.email_test.body', [], 'en'));
    }

    private function fail(User $actor, ResolvedCredentials $credentials, EmailAddress $recipient, ProviderErrorKind $kind): never
    {
        $this->record($actor, $credentials, $recipient, $kind);

        throw IntegrationRejected::providerError($kind);
    }

    private function record(User $actor, ResolvedCredentials $credentials, EmailAddress $recipient, ?ProviderErrorKind $kind): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::EmailTestSent, $actor, [], [
            'mode' => $credentials->mode->value,
            'recipient' => $recipient->masked(),
            'ok' => ! $kind instanceof ProviderErrorKind,
            'error_kind' => $kind?->value,
        ]));
    }
}
