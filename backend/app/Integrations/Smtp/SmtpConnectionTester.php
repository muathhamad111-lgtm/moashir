<?php

declare(strict_types=1);

namespace App\Integrations\Smtp;

use App\Contracts\Integrations\IntegrationConnectionTester;
use App\Contracts\Integrations\SmtpTransportFactory;
use App\Data\Integrations\ConnectionCheck;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\ProviderHttp;
use App\Support\Integrations\SmtpFailure;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final readonly class SmtpConnectionTester implements IntegrationConnectionTester
{
    public function __construct(private SmtpTransportFactory $transports, private MailPolicyRecords $policies) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Smtp;
    }

    public function test(ResolvedCredentials $credentials): ConnectionTestResult
    {
        $startedAt = (int) hrtime(true);
        $info = $this->policies->of($credentials->string('from_address'));

        try {
            $transport = $this->transports->build($credentials);
        } catch (ProviderCallFailed $failure) {
            return $this->result($credentials, $startedAt, [ConnectionCheck::failed('host_allowed', 'host_not_allowed')], $info, $failure->kind);
        }

        try {
            $transport->start();
            $transport->stop();
        } catch (TransportExceptionInterface $exception) {
            $kind = SmtpFailure::kindOf($exception);

            return $this->result($credentials, $startedAt, [ConnectionCheck::passed('host_allowed'), ConnectionCheck::failed('handshake', $kind->value)], $info, $kind);
        }

        return $this->result($credentials, $startedAt, [ConnectionCheck::passed('host_allowed'), ConnectionCheck::passed('handshake')], $info);
    }

    /**
     * @param  list<ConnectionCheck>  $checks
     * @param  array<string, scalar|null>  $info
     */
    private function result(ResolvedCredentials $credentials, int $startedAt, array $checks, array $info, ?ProviderErrorKind $error = null): ConnectionTestResult
    {
        return new ConnectionTestResult($this->provider(), $credentials->mode, ProviderHttp::elapsedMilliseconds($startedAt), $checks, $info, $error);
    }
}
