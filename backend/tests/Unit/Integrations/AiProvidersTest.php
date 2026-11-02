<?php

declare(strict_types=1);

use App\Data\Integrations\AiPrompt;
use App\Enums\Integrations\AiAnswerFinish;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Integrations\Anthropic\AnthropicProvider;
use App\Integrations\OpenAi\OpenAiProvider;
use App\Services\Integrations\IntegrationCredentialsResolver;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function testPrompt(): AiPrompt
{
    return new AiPrompt('Answer only from the excerpts.', 'What is the accredited assessment?', ['The accredited assessment is reviewed by an assessor.'], 256);
}

it('maps an incomplete or refused OpenAI response', function (array $response, AiAnswerFinish $finish, string $text): void {
    configureOpenAi();
    fakeProviderRoutes(['POST api.openai.com/v1/responses' => Http::response($response)]);

    $answer = app(OpenAiProvider::class)->answer(app(IntegrationCredentialsResolver::class)->forProvider(IntegrationProvider::OpenAi), testPrompt());

    expect($answer->finish)->toBe($finish)->and($answer->text)->toBe($text);
    Http::assertSent(static fn (ClientRequest $request): bool => str_contains((string) $request['input'], 'reviewed by an assessor') && $request['instructions'] === 'Answer only from the excerpts.');
})->with([
    'incomplete' => [['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'], 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'The accredited']]]]], AiAnswerFinish::Truncated, 'The accredited'],
    'refusal' => [['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No.']]]]], AiAnswerFinish::Refused, ''],
]);

it('maps the Anthropic stop reasons and keeps text blocks only', function (string $stopReason, AiAnswerFinish $finish): void {
    configureAnthropic();
    fakeProviderRoutes(['POST api.anthropic.com/v1/messages' => Http::response([
        'content' => [['type' => 'thinking', 'thinking' => 'hidden'], ['type' => 'text', 'text' => 'Reviewed by an assessor.']],
        'stop_reason' => $stopReason, 'usage' => ['input_tokens' => 40, 'output_tokens' => 6],
    ])]);

    $answer = app(AnthropicProvider::class)->answer(app(IntegrationCredentialsResolver::class)->forProvider(IntegrationProvider::Anthropic), testPrompt());

    expect($answer->finish)->toBe($finish)
        ->and($answer->text)->toBe('Reviewed by an assessor.')
        ->and($answer->outputTokens)->toBe(6);
    Http::assertSent(static fn (ClientRequest $request): bool => ! isset($request['inference_geo']) && $request['system'] === 'Answer only from the excerpts.');
})->with([['end_turn', AiAnswerFinish::Complete], ['max_tokens', AiAnswerFinish::Truncated], ['refusal', AiAnswerFinish::Refused]]);

it('maps the AI provider errors', function (string $provider, int $status, array $body, array $headers, ProviderErrorKind $kind): void {
    $provider === 'openai' ? configureOpenAi() : configureAnthropic();
    $host = $provider === 'openai' ? 'api.openai.com/v1/responses' : 'api.anthropic.com/v1/messages';
    Sleep::fake();
    fakeProviderRoutes(['POST '.$host => Http::response($body, $status, $headers)]);
    $adapter = $provider === 'openai' ? app(OpenAiProvider::class) : app(AnthropicProvider::class);

    expect(fn () => $adapter->answer(app(IntegrationCredentialsResolver::class)->forProvider(IntegrationProvider::from($provider)), testPrompt()))
        ->toThrow(static fn (ProviderCallFailed $failure): bool => $failure->kind === $kind);
})->with([
    'OpenAI bad key' => ['openai', 401, ['error' => ['type' => 'invalid_request_error', 'code' => 'invalid_api_key']], [], ProviderErrorKind::AuthFailed],
    'OpenAI unsupported country' => ['openai', 403, ['error' => ['code' => 'unsupported_country_region_territory']], [], ProviderErrorKind::AuthFailed],
    'OpenAI rate limit' => ['openai', 429, ['error' => ['code' => 'rate_limit_exceeded']], ['retry-after' => '20'], ProviderErrorKind::RateLimited],
    'OpenAI too large' => ['openai', 413, ['error' => ['code' => 'context_length_exceeded']], [], ProviderErrorKind::RequestTooLarge],
    'Anthropic billing' => ['anthropic', 402, ['type' => 'error', 'error' => ['type' => 'billing_error', 'message' => 'Payment required']], [], ProviderErrorKind::QuotaExhausted],
    'Anthropic spend cap' => ['anthropic', 429, ['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'Spend limit reached']], [], ProviderErrorKind::QuotaExhausted],
    'Anthropic own limit' => ['anthropic', 400, ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low']], [], ProviderErrorKind::QuotaExhausted],
    'Anthropic bad request' => ['anthropic', 400, ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'max_tokens too large']], [], ProviderErrorKind::Rejected],
]);
