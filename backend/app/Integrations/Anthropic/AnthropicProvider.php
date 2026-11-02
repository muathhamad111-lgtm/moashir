<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic;

use App\Contracts\Integrations\AiModelCatalog;
use App\Contracts\Integrations\AiProvider;
use App\Data\Integrations\AiAnswer;
use App\Data\Integrations\AiModelInfo;
use App\Data\Integrations\AiPrompt;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\AiAnswerFinish;
use App\Enums\Integrations\AnthropicInferenceGeo;
use App\Enums\Integrations\IntegrationProvider;
use App\Services\Integrations\ProviderHttp;
use App\Support\ScalarValue;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;

final readonly class AnthropicProvider implements AiModelCatalog, AiProvider
{
    private const int PAGE_SIZE = 1000;

    public function __construct(private AnthropicClient $client, private Repository $config) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Anthropic;
    }

    public function models(ResolvedCredentials $credentials): array
    {
        $models = $this->client->get($credentials, 'list_models', '/v1/models', ['limit' => self::PAGE_SIZE], $this->config->integer('moashir.integrations.timeout_seconds.ai_catalog'))->json('data');
        $list = [];

        foreach (is_array($models) ? $models : [] as $model) {
            if (! is_array($model) || ! is_string($model['id'] ?? null) || $this->isRetired($model['retires_at'] ?? null)) {
                continue;
            }

            $maxTokens = $model['max_tokens'] ?? null;
            $list[] = new AiModelInfo($model['id'], ScalarValue::string($model['display_name'] ?? null, $model['id']), is_int($maxTokens) ? $maxTokens : null);
        }

        return $list;
    }

    public function answer(ResolvedCredentials $credentials, AiPrompt $prompt): AiAnswer
    {
        $startedAt = (int) hrtime(true);
        $body = [
            'model' => $credentials->string('model'),
            'max_tokens' => $prompt->maxOutputTokens,
            'system' => $prompt->systemInstructions,
            'messages' => [['role' => 'user', 'content' => $prompt->userContent()]],
        ];

        if (AnthropicInferenceGeo::tryFrom($credentials->string('inference_geo')) === AnthropicInferenceGeo::Us) {
            $body['inference_geo'] = AnthropicInferenceGeo::Us->value;
        }

        $response = $this->client->post($credentials, 'create_message', '/v1/messages', $body, $credentials->int('timeout_seconds', $this->config->integer('moashir.integrations.timeout_seconds.ai_answer')));

        return new AiAnswer(
            $this->textOf($response->json('content')),
            match ($response->json('stop_reason')) {
                'max_tokens' => AiAnswerFinish::Truncated,
                'refusal' => AiAnswerFinish::Refused,
                default => AiAnswerFinish::Complete,
            },
            ScalarValue::int($response->json('usage.input_tokens')),
            ScalarValue::int($response->json('usage.output_tokens')),
            $response->header('request-id') ?: null,
            ProviderHttp::elapsedMilliseconds($startedAt),
        );
    }

    private function isRetired(mixed $retiresAt): bool
    {
        return is_string($retiresAt) && CarbonImmutable::parse($retiresAt)->isPast();
    }

    private function textOf(mixed $content): string
    {
        $parts = [];

        foreach (is_array($content) ? $content : [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return implode('', $parts);
    }
}
