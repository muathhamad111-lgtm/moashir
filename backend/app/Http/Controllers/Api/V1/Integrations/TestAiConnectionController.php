<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\TestIntegrationConnection;
use App\Enums\Integrations\IntegrationKind;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Http\Requests\Integrations\AiProviderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationRows;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class TestAiConnectionController
{
    public function __invoke(AiProviderRequest $request, #[CurrentUser] User $actor, TestIntegrationConnection $test, IntegrationRows $rows): JsonResponse
    {
        $provider = $request->provider() ?? $rows->active(IntegrationKind::Ai)->provider ?? throw IntegrationRejected::notConfigured(IntegrationKind::Ai);

        return ApiResponse::data($test->handle($actor, $provider)->toArray());
    }
}
