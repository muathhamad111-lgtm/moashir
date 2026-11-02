<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Integrations\SmtpTransportFactory;
use App\Data\Integrations\ResolvedCredentials;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\RawMessage;

final class RecordingSmtpTransportFactory implements SmtpTransportFactory
{
    /**
     * @var list<RawMessage>
     */
    public array $sent = [];

    public int $builds = 0;

    public int $starts = 0;

    public function __construct(private readonly ?string $failure = null, private readonly int $failureCode = 0) {}

    public function build(ResolvedCredentials $credentials): EsmtpTransport
    {
        $this->builds++;
        $factory = $this;

        return new class($factory) extends EsmtpTransport
        {
            public function __construct(private readonly RecordingSmtpTransportFactory $factory)
            {
                parent::__construct('smtp.example.sa', 587, false);
            }

            public function start(): void
            {
                $this->factory->starts++;
                $this->factory->failIfConfigured();
            }

            public function stop(): void {}

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->factory->failIfConfigured();
                $this->factory->sent[] = $message;

                return null;
            }
        };
    }

    public function failIfConfigured(): void
    {
        if ($this->failure !== null) {
            throw new TransportException($this->failure, $this->failureCode);
        }
    }
}
