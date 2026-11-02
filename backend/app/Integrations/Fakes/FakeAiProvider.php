<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Integrations\AiModelCatalog;
use App\Contracts\Integrations\AiProvider;
use App\Data\Integrations\AiAnswer;
use App\Data\Integrations\AiModelInfo;
use App\Data\Integrations\AiPrompt;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\AiAnswerFinish;
use App\Enums\Integrations\IntegrationProvider;

final class FakeAiProvider implements AiModelCatalog, AiProvider
{
    /**
     * @var list<AiPrompt>
     */
    private array $prompts = [];

    public function __construct(private readonly IntegrationProvider $provider = IntegrationProvider::OpenAi) {}

    public function provider(): IntegrationProvider
    {
        return $this->provider;
    }

    public function models(ResolvedCredentials $credentials): array
    {
        return [new AiModelInfo('fake-model-small', 'Fake model small', 1024)];
    }

    public function answer(ResolvedCredentials $credentials, AiPrompt $prompt): AiAnswer
    {
        $this->prompts[] = $prompt;

        return new AiAnswer('ok', AiAnswerFinish::Complete, 12, 1, 'fake-request', 1);
    }

    /**
     * @return list<AiPrompt>
     */
    public function prompts(): array
    {
        return $this->prompts;
    }
}
