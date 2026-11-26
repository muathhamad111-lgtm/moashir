<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Actions\Notifications\ListOwnNotificationPreferences;
use App\Actions\Notifications\UpdateOwnNotificationPreferences;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Notifications\NotificationPreferenceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class NotificationPreferenceController
{
    public function show(#[CurrentUser] User $user, ListOwnNotificationPreferences $listPreferences): JsonResponse
    {
        return ApiResponse::data(NotificationPreferenceResource::collection($listPreferences->handle($user))->resolve(request()));
    }

    public function update(UpdateNotificationPreferencesRequest $request, #[CurrentUser] User $user, UpdateOwnNotificationPreferences $updatePreferences): JsonResponse
    {
        return ApiResponse::data(NotificationPreferenceResource::collection($updatePreferences->handle($user, $request->changes()))->resolve($request));
    }
}
