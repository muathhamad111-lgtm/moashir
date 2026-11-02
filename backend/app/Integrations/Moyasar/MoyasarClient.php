<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Data\Integrations\OutboundCall;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\ProviderHttp;
use App\Support\Integrations\EgressAllowList;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

final readonly class MoyasarClient
{
    private const string API_PREFIX = '/v1';

    private const int JITTER_MILLISECONDS = 250;

    public function __construct(private ProviderHttp $http, private Repository $config) {}

    /**
     * @param  array<string, scalar>  $query
     */
    public function get(ResolvedCredentials $credentials, string $operation, string $path, array $query = [], int $retries = 0): Response
    {
        return $this->call($credentials, $operation, $retries, static fn (PendingRequest $request): Response => $request->get(self::API_PREFIX.$path, $query));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(ResolvedCredentials $credentials, string $operation, string $path, array $body = [], int $retries = 0): Response
    {
        return $this->call($credentials, $operation, $retries, static fn (PendingRequest $request): Response => $request->asJson()->post(self::API_PREFIX.$path, $body));
    }

    public function delete(ResolvedCredentials $credentials, string $operation, string $path): Response
    {
        return $this->call($credentials, $operation, 0, static fn (PendingRequest $request): Response => $request->delete(self::API_PREFIX.$path));
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     */
    private function call(ResolvedCredentials $credentials, string $operation, int $retries, callable $send): Response
    {
        if (! $credentials->isConfigured() || ! $credentials->hasSecret('secret_key')) {
            throw ProviderCallFailed::because(IntegrationProvider::Moyasar, $operation, ProviderErrorKind::Misconfigured);
        }

        $call = new OutboundCall(IntegrationProvider::Moyasar, $operation, EgressAllowList::MOYASAR, $this->config->integer('moashir.integrations.timeout_seconds.payment'));
        $secretKey = $credentials->secret('secret_key')->reveal();

        $response = $this->http->send($call, static function (PendingRequest $request) use ($secretKey, $retries, $send): Response {
            $request->withBasicAuth($secretKey, '');

            if ($retries > 0) {
                $request->retry($retries + 1, self::backoff(...), self::isTransient(...), throw: false);
            }

            return $send($request);
        });

        MoyasarErrors::throwIfFailed($response, $operation);

        return $response;
    }

    private static function backoff(int $attempt): int
    {
        return (1000 * (2 ** max(0, $attempt - 1))) + random_int(0, self::JITTER_MILLISECONDS);
    }

    private static function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->status() === 429 || $exception->response->serverError());
    }
}
