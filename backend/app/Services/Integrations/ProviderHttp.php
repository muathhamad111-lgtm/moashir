<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Integrations\OutboundCall;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Support\Integrations\EgressAllowList;
use App\Support\RequestContext;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Psr\Log\LoggerInterface;

final readonly class ProviderHttp
{
    private const array PROVIDER_REQUEST_ID_HEADERS = ['x-request-id', 'request-id', 'x-moyasar-request-id'];

    public function __construct(
        private Factory $http,
        private Repository $config,
        private LoggerInterface $logger,
        private RequestContext $context,
    ) {}

    /**
     * @param  Closure(PendingRequest): Response  $send
     */
    public function send(OutboundCall $call, Closure $send): Response
    {
        $request = $this->http
            ->baseUrl(EgressAllowList::httpsUrl($call->host))
            ->connectTimeout($this->config->integer('moashir.integrations.connect_timeout_seconds'))
            ->timeout($call->timeoutSeconds)
            ->acceptJson()
            ->withOptions(['allow_redirects' => false, 'verify' => true]);

        $startedAt = (int) hrtime(true);

        try {
            $response = $send($request);
        } catch (ConnectionException $exception) {
            $kind = str_contains(mb_strtolower($exception->getMessage()), 'timed out') ? ProviderErrorKind::Timeout : ProviderErrorKind::Unavailable;
            $this->log($call, null, $startedAt, null);

            throw ProviderCallFailed::because($call->provider, $call->operation, $kind);
        }

        $this->log($call, $response->status(), $startedAt, $this->providerRequestId($response));

        return $response;
    }

    public static function elapsedMilliseconds(int $startedAt): int
    {
        return intdiv((int) hrtime(true) - $startedAt, 1_000_000);
    }

    private function providerRequestId(Response $response): ?string
    {
        foreach (self::PROVIDER_REQUEST_ID_HEADERS as $header) {
            $value = $response->header($header);

            if ($value !== '') {
                return mb_substr($value, 0, 100);
            }
        }

        return null;
    }

    private function log(OutboundCall $call, ?int $status, int $startedAt, ?string $providerRequestId): void
    {
        $this->logger->info('integration.outbound_call', [
            'provider' => $call->provider->value,
            'operation' => $call->operation,
            'status' => $status,
            'latency_ms' => self::elapsedMilliseconds($startedAt),
            'request_id' => $this->context->requestId(),
            'provider_request_id' => $providerRequestId,
        ]);
    }
}
