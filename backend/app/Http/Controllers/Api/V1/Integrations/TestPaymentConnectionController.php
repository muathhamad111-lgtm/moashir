<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\TestIntegrationConnection;
use App\Enums\Integrations\IntegrationProvider;
use App\Http\Requests\Integrations\TestPaymentConnectionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class TestPaymentConnectionController
{
    public function __invoke(TestPaymentConnectionRequest $request, #[CurrentUser] User $actor, TestIntegrationConnection $test): JsonResponse
    {
        return ApiResponse::data($test->handle($actor, IntegrationProvider::Moyasar, $request->mode())->toArray());
    }
}
