<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\ListAiModels;
use App\Data\Integrations\AiModelInfo;
use App\Enums\Integrations\IntegrationProvider;
use App\Http\Requests\Integrations\AiProviderRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AiModelController
{
    public function __invoke(AiProviderRequest $request, ListAiModels $models): JsonResponse
    {
        $provider = $request->provider() ?? IntegrationProvider::OpenAi;

        return ApiResponse::data(array_map(static fn (AiModelInfo $model): array => $model->toArray(), $models->handle($provider)), meta: ['provider' => $provider->value]);
    }
}
