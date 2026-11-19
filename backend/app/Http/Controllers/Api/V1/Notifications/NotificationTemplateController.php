<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Actions\Notifications\ResetNotificationText;
use App\Actions\Notifications\SaveNotificationText;
use App\Actions\Notifications\ShowNotificationType;
use App\Actions\Notifications\UpdateNotificationChannels;
use App\Enums\Notifications\NotificationCode;
use App\Http\Requests\Notifications\SaveNotificationTextRequest;
use App\Http\Requests\Notifications\UpdateNotificationChannelsRequest;
use App\Http\Resources\Notifications\NotificationTypeDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Notifications\Templates\ShippedNotificationTemplates;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NotificationTemplateController
{
    public function show(Request $request, NotificationCode $code, ShowNotificationType $showType): JsonResponse
    {
        return ApiResponse::data(NotificationTypeDetailResource::make($showType->handle($code))->resolve($request));
    }

    public function update(SaveNotificationTextRequest $request, #[CurrentUser] User $actor, SaveNotificationText $save, ShowNotificationType $showType): JsonResponse
    {
        $change = $request->change();
        $save->handle($actor, $change);

        return ApiResponse::data(NotificationTypeDetailResource::make($showType->handle($change->template->code))->resolve($request));
    }

    public function updateChannels(NotificationCode $code, UpdateNotificationChannelsRequest $request, #[CurrentUser] User $actor, UpdateNotificationChannels $update, ShowNotificationType $showType): JsonResponse
    {
        $update->handle($actor, $code, $request->choices());

        return ApiResponse::data(NotificationTypeDetailResource::make($showType->handle($code))->resolve($request));
    }

    public function destroy(string $templateKey, ShippedNotificationTemplates $templates, ResetNotificationText $reset): Response
    {
        $reset->handle($templates->catalog()->find($templateKey) ?? abort(404));

        return response()->noContent();
    }
}
