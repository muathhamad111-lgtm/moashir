<?php

declare(strict_types=1);

namespace App\Integrations\Smtp;

use App\Contracts\Integrations\SmtpTransportFactory;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Integrations\SmtpEncryption;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\SmtpHostGuard;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

final readonly class SmtpTransportBuilder implements SmtpTransportFactory
{
    private const int DEFAULT_TIMEOUT_SECONDS = 15;

    public function __construct(private SmtpHostGuard $guard) {}

    public function build(ResolvedCredentials $credentials): EsmtpTransport
    {
        $host = $credentials->string('host');
        $port = $credentials->int('port', 587);
        $address = $this->guard->isAllowedPort($port) ? $this->guard->publicAddressOf($host) : null;

        if ($address === null) {
            throw ProviderCallFailed::because(IntegrationProvider::Smtp, 'connect', ProviderErrorKind::Misconfigured);
        }

        $encryption = SmtpEncryption::tryFrom($credentials->string('encryption')) ?? SmtpEncryption::StartTls;
        $transport = new EsmtpTransport(str_contains($address, ':') ? '['.$address.']' : $address, $port, $encryption->usesImplicitTls());
        $transport->setRequireTls(! $encryption->usesImplicitTls());
        $transport->setUsername($credentials->string('username'));
        $transport->setPassword($credentials->secret('password')->reveal());
        $transport->setLocalDomain($credentials->string('ehlo_domain') ?: 'localhost');

        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            $stream->setTimeout((float) $credentials->int('timeout_seconds', self::DEFAULT_TIMEOUT_SECONDS));
            $stream->setStreamOptions(['ssl' => ['peer_name' => $host, 'SNI_enabled' => true, 'verify_peer' => true, 'verify_peer_name' => true]]);
        }

        return $transport;
    }
}
