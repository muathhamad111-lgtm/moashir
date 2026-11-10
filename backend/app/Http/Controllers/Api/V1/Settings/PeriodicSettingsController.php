<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\PreviewPeriodicSettings;
use App\Actions\Settings\UpdatePeriodicSettings;
use App\Http\Requests\Settings\PreviewPeriodicSettingsRequest;
use App\Http\Requests\Settings\UpdatePeriodicSettingsRequest;
use App\Http\Resources\Settings\PeriodicSettingsPreviewResource;
use App\Http\Resources\Settings\PeriodicSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Periods\PeriodicSettingsViewReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PeriodicSettingsController
{
    public function show(PeriodicSettingsViewReader $reader): JsonResponse
    {
        return ApiResponse::resource(new PeriodicSettingsResource($reader->read()));
    }

    public function update(UpdatePeriodicSettingsRequest $request, #[CurrentUser] User $actor, UpdatePeriodicSettings $update, PeriodicSettingsViewReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new PeriodicSettingsResource($reader->read()));
    }

    public function preview(PreviewPeriodicSettingsRequest $request, PreviewPeriodicSettings $preview): JsonResponse
    {
        return ApiResponse::resource(new PeriodicSettingsPreviewResource($preview->handle($request->toUpdate())));
    }
}
