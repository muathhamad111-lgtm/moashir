<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdateAuthSettings;
use App\Http\Requests\Settings\UpdateAuthSettingsRequest;
use App\Http\Resources\Settings\AuthSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Settings\AuthSettingsReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AuthSettingsController
{
    public function show(AuthSettingsReader $reader): JsonResponse
    {
        return ApiResponse::resource(new AuthSettingsResource($reader->read()));
    }

    public function update(UpdateAuthSettingsRequest $request, #[CurrentUser] User $actor, UpdateAuthSettings $update, AuthSettingsReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new AuthSettingsResource($reader->read()));
    }
}
