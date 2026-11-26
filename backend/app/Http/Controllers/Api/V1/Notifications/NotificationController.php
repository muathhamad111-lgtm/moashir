<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Actions\Notifications\CountUnreadNotifications;
use App\Actions\Notifications\ListOwnNotifications;
use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Http\Resources\Notifications\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class NotificationController
{
    public function index(ListNotificationsRequest $request, #[CurrentUser] User $user, ListOwnNotifications $listNotifications, CountUnreadNotifications $countUnread): JsonResponse
    {
        return ApiResponse::paginated($listNotifications->handle($user, $request->filters()), NotificationResource::class, [
            'unread_count' => $countUnread->handle($user)->total,
        ]);
    }

    public function unreadCount(#[CurrentUser] User $user, CountUnreadNotifications $countUnread): JsonResponse
    {
        $counts = $countUnread->handle($user);

        return ApiResponse::data(['count' => $counts->total, 'by_category' => $counts->byCategory]);
    }
}
