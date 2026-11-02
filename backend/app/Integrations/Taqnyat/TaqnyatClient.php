<?php

declare(strict_types=1);

namespace App\Integrations\Taqnyat;

use App\Data\Integrations\OutboundCall;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\ProviderHttp;
use App\Support\Integrations\EgressAllowList;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

final readonly class TaqnyatClient
{
    public function __construct(private ProviderHttp $http, private Repository $config) {}

    public function status(): Response
    {
        return $this->http->send($this->call('system_status'), static fn (PendingRequest $request): Response => $request->get('/system/status'));
    }

    public function get(ResolvedCredentials $credentials, string $operation, string $path): Response
    {
        return $this->authorized($credentials, $operation, static fn (PendingRequest $request): Response => $request->get($path));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(ResolvedCredentials $credentials, string $operation, string $path, array $body): Response
    {
        return $this->authorized($credentials, $operation, static fn (PendingRequest $request): Response => $request->asJson()->post($path, $body));
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     */
    private function authorized(ResolvedCredentials $credentials, string $operation, callable $send): Response
    {
        if (! $credentials->hasSecret('bearer_token')) {
            throw ProviderCallFailed::because(IntegrationProvider::Taqnyat, $operation, ProviderErrorKind::Misconfigured);
        }

        $token = $credentials->secret('bearer_token')->reveal();
        $response = $this->http->send($this->call($operation), static fn (PendingRequest $request): Response => $send($request->withToken($token)));

        TaqnyatErrors::throwIfFailed($response, $operation);

        return $response;
    }

    private function call(string $operation): OutboundCall
    {
        return new OutboundCall(IntegrationProvider::Taqnyat, $operation, EgressAllowList::TAQNYAT, $this->config->integer('moashir.integrations.timeout_seconds.sms'));
    }
}
