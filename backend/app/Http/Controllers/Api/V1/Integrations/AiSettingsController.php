<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\UpdateAiSettings;
use App\Enums\Integrations\IntegrationKind;
use App\Http\Requests\Integrations\UpdateAiSettingsRequest;
use App\Http\Resources\Integrations\AiSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationSnapshotReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AiSettingsController
{
    public function show(IntegrationSnapshotReader $reader): JsonResponse
    {
        return ApiResponse::resource(new AiSettingsResource($reader->read(IntegrationKind::Ai)));
    }

    public function update(UpdateAiSettingsRequest $request, #[CurrentUser] User $actor, UpdateAiSettings $update, IntegrationSnapshotReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new AiSettingsResource($reader->read(IntegrationKind::Ai)));
    }
}
