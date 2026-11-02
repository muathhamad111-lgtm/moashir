<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic;

use App\Data\Integrations\OutboundCall;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Integrations\Ai\AiRetryPolicy;
use App\Services\Integrations\ProviderHttp;
use App\Support\Integrations\EgressAllowList;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

final readonly class AnthropicClient
{
    public const string API_VERSION = '2023-06-01';

    public function __construct(private ProviderHttp $http) {}

    /**
     * @param  array<string, scalar>  $query
     */
    public function get(ResolvedCredentials $credentials, string $operation, string $path, array $query, int $timeoutSeconds): Response
    {
        return $this->send($credentials, $operation, $timeoutSeconds, static fn (PendingRequest $request): Response => $request->get($path, $query));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(ResolvedCredentials $credentials, string $operation, string $path, array $body, int $timeoutSeconds): Response
    {
        return $this->send($credentials, $operation, $timeoutSeconds, static fn (PendingRequest $request): Response => $request
            ->retry(2, AiRetryPolicy::delay(...), AiRetryPolicy::shouldRetry(...), throw: false)
            ->asJson()
            ->post($path, $body));
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     */
    private function send(ResolvedCredentials $credentials, string $operation, int $timeoutSeconds, callable $send): Response
    {
        if (! $credentials->hasSecret('api_key')) {
            throw ProviderCallFailed::because(IntegrationProvider::Anthropic, $operation, ProviderErrorKind::Misconfigured);
        }

        $headers = array_filter([
            'x-api-key' => $credentials->secret('api_key')->reveal(),
            'anthropic-version' => self::API_VERSION,
            'anthropic-workspace-id' => $credentials->string('workspace_id'),
        ], static fn (string $value): bool => $value !== '');

        $response = $this->http->send(
            new OutboundCall(IntegrationProvider::Anthropic, $operation, EgressAllowList::ANTHROPIC, $timeoutSeconds),
            static fn (PendingRequest $request): Response => $send($request->withHeaders($headers)),
        );

        AnthropicErrors::throwIfFailed($response, $operation);

        return $response;
    }
}
