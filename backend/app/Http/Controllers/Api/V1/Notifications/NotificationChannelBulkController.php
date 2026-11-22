<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Actions\Notifications\BulkUpdateNotificationChannels;
use App\Actions\Notifications\ListNotificationTypes;
use App\Data\Notifications\NotificationTypeFilters;
use App\Http\Requests\Notifications\BulkUpdateNotificationChannelsRequest;
use App\Http\Resources\Notifications\NotificationTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class NotificationChannelBulkController
{
    public function __invoke(BulkUpdateNotificationChannelsRequest $request, #[CurrentUser] User $actor, BulkUpdateNotificationChannels $update, ListNotificationTypes $listTypes): JsonResponse
    {
        $updated = $update->handle($actor, $request->change());
        $types = $listTypes->handle(new NotificationTypeFilters(null, null, null));

        return ApiResponse::data(NotificationTypeResource::collection($types)->resolve($request), meta: ['total' => count($types), 'updated' => $updated]);
    }
}
