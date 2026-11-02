<?php

declare(strict_types=1);

namespace App\Integrations\OpenAi;

use App\Data\Integrations\OutboundCall;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\OpenAiRegion;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Integrations\Ai\AiRetryPolicy;
use App\Services\Integrations\ProviderHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

final readonly class OpenAiClient
{
    public function __construct(private ProviderHttp $http) {}

    public function get(ResolvedCredentials $credentials, string $operation, string $path, int $timeoutSeconds): Response
    {
        return $this->send($credentials, $operation, $timeoutSeconds, static fn (PendingRequest $request): Response => $request->get($path));
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
            throw ProviderCallFailed::because(IntegrationProvider::OpenAi, $operation, ProviderErrorKind::Misconfigured);
        }

        $region = OpenAiRegion::tryFrom($credentials->string('region')) ?? OpenAiRegion::Global;
        $headers = array_filter([
            'OpenAI-Organization' => $credentials->string('organization_id'),
            'OpenAI-Project' => $credentials->string('project_id'),
        ], static fn (string $value): bool => $value !== '');
        $apiKey = $credentials->secret('api_key')->reveal();

        $response = $this->http->send(
            new OutboundCall(IntegrationProvider::OpenAi, $operation, $region->host(), $timeoutSeconds),
            static fn (PendingRequest $request): Response => $send($request->withToken($apiKey)->withHeaders($headers)),
        );

        OpenAiErrors::throwIfFailed($response, $operation);

        return $response;
    }
}
