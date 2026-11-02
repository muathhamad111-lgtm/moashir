<?php

declare(strict_types=1);

namespace App\Integrations\OpenAi;

use App\Contracts\Integrations\AiModelCatalog;
use App\Contracts\Integrations\AiProvider;
use App\Data\Integrations\AiAnswer;
use App\Data\Integrations\AiModelInfo;
use App\Data\Integrations\AiPrompt;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\AiAnswerFinish;
use App\Enums\Integrations\IntegrationProvider;
use App\Services\Integrations\ProviderHttp;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;

final readonly class OpenAiProvider implements AiModelCatalog, AiProvider
{
    private const array CHAT_PREFIXES = ['gpt-', 'chatgpt-', 'o1', 'o3', 'o4'];

    private const array NON_CHAT_MARKERS = ['embedding', 'tts', 'whisper', 'dall-e', 'audio', 'realtime', 'transcribe', 'image', 'moderation', 'search', 'instruct'];

    public function __construct(private OpenAiClient $client, private Repository $config) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::OpenAi;
    }

    public function models(ResolvedCredentials $credentials): array
    {
        $models = $this->client->get($credentials, 'list_models', '/v1/models', $this->config->integer('moashir.integrations.timeout_seconds.ai_catalog'))->json('data');
        $list = [];

        foreach (is_array($models) ? $models : [] as $model) {
            $id = is_array($model) ? ScalarValue::string($model['id'] ?? null) : '';

            if ($this->isChatModel($id)) {
                $list[] = new AiModelInfo($id, $id);
            }
        }

        usort($list, static fn (AiModelInfo $left, AiModelInfo $right): int => strcmp($left->id, $right->id));

        return $list;
    }

    public function answer(ResolvedCredentials $credentials, AiPrompt $prompt): AiAnswer
    {
        $startedAt = (int) hrtime(true);
        $response = $this->client->post($credentials, 'create_response', '/v1/responses', [
            'model' => $credentials->string('model'),
            'instructions' => $prompt->systemInstructions,
            'input' => $prompt->userContent(),
            'max_output_tokens' => $prompt->maxOutputTokens,
            'store' => false,
        ], $credentials->int('timeout_seconds', $this->config->integer('moashir.integrations.timeout_seconds.ai_answer')));

        [$text, $refused] = $this->outputOf($response->json('output'));
        $finish = match (true) {
            $refused => AiAnswerFinish::Refused,
            $response->json('status') === 'incomplete' => AiAnswerFinish::Truncated,
            default => AiAnswerFinish::Complete,
        };

        return new AiAnswer(
            $text,
            $finish,
            ScalarValue::int($response->json('usage.input_tokens')),
            ScalarValue::int($response->json('usage.output_tokens')),
            $response->header('x-request-id') ?: null,
            ProviderHttp::elapsedMilliseconds($startedAt),
        );
    }

    private function isChatModel(string $id): bool
    {
        $isChat = array_any(self::CHAT_PREFIXES, static fn (string $prefix): bool => str_starts_with($id, $prefix));

        return $isChat && ! array_any(self::NON_CHAT_MARKERS, static fn (string $marker): bool => str_contains($id, $marker));
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function outputOf(mixed $output): array
    {
        $parts = [];
        $refused = false;

        foreach (is_array($output) ? $output : [] as $item) {
            $content = is_array($item) && is_array($item['content'] ?? null) ? $item['content'] : [];

            foreach ($content as $block) {
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                $refused = $refused || $type === 'refusal';

                if ($type === 'output_text' && is_string($block['text'] ?? null)) {
                    $parts[] = $block['text'];
                }
            }
        }

        return [implode('', $parts), $refused];
    }
}
