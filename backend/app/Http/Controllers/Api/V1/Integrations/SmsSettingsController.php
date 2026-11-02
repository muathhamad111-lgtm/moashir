<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Integrations\UpdateSmsSettings;
use App\Enums\Integrations\IntegrationKind;
use App\Http\Requests\Integrations\UpdateSmsSettingsRequest;
use App\Http\Resources\Integrations\SmsSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Integrations\IntegrationSnapshotReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SmsSettingsController
{
    public function show(IntegrationSnapshotReader $reader): JsonResponse
    {
        return ApiResponse::resource(new SmsSettingsResource($reader->read(IntegrationKind::Sms)));
    }

    public function update(UpdateSmsSettingsRequest $request, #[CurrentUser] User $actor, UpdateSmsSettings $update, IntegrationSnapshotReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new SmsSettingsResource($reader->read(IntegrationKind::Sms)));
    }
}
