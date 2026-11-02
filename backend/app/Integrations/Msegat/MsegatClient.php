<?php

declare(strict_types=1);

namespace App\Integrations\Msegat;

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

final readonly class MsegatClient
{
    private const string GATEWAY_PATH = '/gw/';

    public function __construct(private ProviderHttp $http, private Repository $config) {}

    /**
     * @param  array<string, string>  $parameters
     */
    public function postJson(ResolvedCredentials $credentials, string $operation, string $script, array $parameters = []): Response
    {
        $body = [...$this->authentication($credentials, $operation), ...$parameters];

        return $this->http->send($this->call($operation), static fn (PendingRequest $request): Response => $request->asJson()->post(self::GATEWAY_PATH.$script, $body));
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public function postForm(ResolvedCredentials $credentials, string $operation, string $script, array $parameters = []): Response
    {
        $body = [...$this->authentication($credentials, $operation), ...$parameters];

        return $this->http->send($this->call($operation), static fn (PendingRequest $request): Response => $request->asMultipart()->post(self::GATEWAY_PATH.$script, $body));
    }

    /**
     * @return array{userName: string, apiKey: string}
     */
    private function authentication(ResolvedCredentials $credentials, string $operation): array
    {
        if (! $credentials->hasSecret('api_key') || $credentials->string('username') === '') {
            throw ProviderCallFailed::because(IntegrationProvider::Msegat, $operation, ProviderErrorKind::Misconfigured);
        }

        return ['userName' => $credentials->string('username'), 'apiKey' => $credentials->secret('api_key')->reveal()];
    }

    private function call(string $operation): OutboundCall
    {
        return new OutboundCall(IntegrationProvider::Msegat, $operation, EgressAllowList::MSEGAT, $this->config->integer('moashir.integrations.timeout_seconds.sms'));
    }
}
