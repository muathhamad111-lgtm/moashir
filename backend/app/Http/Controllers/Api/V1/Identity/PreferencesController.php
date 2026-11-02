<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\UpdateOwnPreferences;
use App\Http\Requests\Identity\UpdatePreferencesRequest;
use App\Http\Resources\Identity\PreferencesResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PreferencesController
{
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return ApiResponse::resource(new PreferencesResource($user));
    }

    public function update(UpdatePreferencesRequest $request, #[CurrentUser] User $user, UpdateOwnPreferences $updatePreferences): JsonResponse
    {
        return ApiResponse::resource(new PreferencesResource($updatePreferences->handle($user, $request->changes())));
    }
}
