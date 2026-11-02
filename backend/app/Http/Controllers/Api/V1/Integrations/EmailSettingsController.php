<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\UpdateEmailSettings;
use App\Enums\Integrations\IntegrationKind;
use App\Http\Requests\Integrations\UpdateEmailSettingsRequest;
use App\Http\Resources\Integrations\EmailSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationSnapshotReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class EmailSettingsController
{
    public function show(IntegrationSnapshotReader $reader): JsonResponse
    {
        return ApiResponse::resource(new EmailSettingsResource($reader->read(IntegrationKind::Email)));
    }

    public function update(UpdateEmailSettingsRequest $request, #[CurrentUser] User $actor, UpdateEmailSettings $update, IntegrationSnapshotReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new EmailSettingsResource($reader->read(IntegrationKind::Email)));
    }
}
