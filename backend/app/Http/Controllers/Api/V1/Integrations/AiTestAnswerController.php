<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\RunAiTestAnswer;
use App\Http\Requests\Integrations\AiProviderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AiTestAnswerController
{
    private const int TEXT_LIMIT = 200;

    public function __invoke(AiProviderRequest $request, #[CurrentUser] User $actor, RunAiTestAnswer $run): JsonResponse
    {
        $result = $run->handle($actor, $request->provider());
        $answer = $result->answer;

        return ApiResponse::data([
            'ok' => true,
            'provider' => $result->provider->value,
            'model' => $result->model,
            'finish' => $answer->finish->value,
            'text' => mb_substr($answer->text, 0, self::TEXT_LIMIT),
            'input_tokens' => $answer->inputTokens,
            'output_tokens' => $answer->outputTokens,
            'latency_ms' => $answer->latencyMs,
            'provider_request_id' => $answer->providerRequestId,
        ]);
    }
}
