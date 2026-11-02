<?php

declare(strict_types=1);

namespace App\Integrations\Smtp;

use App\Contracts\Integrations\SmtpTransportFactory;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\IntegrationConfigVersions;
use App\Services\Integrations\IntegrationCredentialsResolver;
use App\Services\Integrations\IntegrationNotifier;
use App\Services\Integrations\OutgoingMailPreparer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\Transport\LogTransport;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

final class SettingsMailTransport implements Stringable, TransportInterface
{
    private ?EsmtpTransport $transport = null;

    private ?ResolvedCredentials $credentials = null;

    private ?int $version = null;

    public function __construct(
        private readonly IntegrationCredentialsResolver $resolver,
        private readonly SmtpTransportFactory $factory,
        private readonly IntegrationConfigVersions $versions,
        private readonly OutgoingMailPreparer $preparer,
        private readonly IntegrationNotifier $notifier,
        private readonly Application $app,
        private readonly LoggerInterface $logger,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $credentials = $this->currentCredentials();

        if (! $credentials->isConfigured()) {
            return $this->sendWithoutConfiguration($message, $envelope);
        }

        try {
            return $this->transport($credentials)->send($this->preparer->prepare($message, $credentials));
        } catch (ProviderCallFailed $failure) {
            throw new TransportException('The e-mail settings are not usable: '.$failure->kind->value.'.', $failure->getCode(), $failure);
        }
    }

    public function __toString(): string
    {
        return 'settings';
    }

    private function currentCredentials(): ResolvedCredentials
    {
        $version = $this->versions->current(IntegrationKind::Email);

        if (! $this->credentials instanceof ResolvedCredentials || $this->version !== $version) {
            $this->transport?->stop();
            $this->transport = null;
            $this->credentials = $this->resolver->resolve(IntegrationKind::Email);
            $this->version = $version;
        }

        return $this->credentials;
    }

    private function transport(ResolvedCredentials $credentials): EsmtpTransport
    {
        return $this->transport ??= $this->factory->build($credentials);
    }

    private function sendWithoutConfiguration(RawMessage $message, ?Envelope $envelope): ?SentMessage
    {
        if ($this->app->environment('local')) {
            return new LogTransport($this->logger)->send($message, $envelope);
        }

        $this->notifier->notifyOnce(NotificationCode::ExternalServiceDown, 'email');

        throw new TransportException('E-mail delivery is not configured.');
    }
}
