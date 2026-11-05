<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Actions\Notifications\ArchiveNotification;
use App\Actions\Notifications\FindOwnNotification;
use App\Actions\Notifications\MarkAllNotificationsRead;
use App\Actions\Notifications\MarkNotificationRead;
use App\Http\Requests\Notifications\MarkAllNotificationsReadRequest;
use App\Http\Resources\Notifications\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final readonly class NotificationStateController
{
    public function __construct(private FindOwnNotification $findNotification) {}

    public function read(string $notification, #[CurrentUser] User $user, MarkNotificationRead $markRead): JsonResponse
    {
        return ApiResponse::resource(new NotificationResource($markRead->handle($this->ownNotification($user, $notification))));
    }

    public function archive(string $notification, #[CurrentUser] User $user, ArchiveNotification $archive): JsonResponse
    {
        return ApiResponse::resource(new NotificationResource($archive->handle($this->ownNotification($user, $notification))));
    }

    public function readAll(MarkAllNotificationsReadRequest $request, #[CurrentUser] User $user, MarkAllNotificationsRead $markAllRead): JsonResponse
    {
        return ApiResponse::data(['updated' => $markAllRead->handle($user, $request->category())]);
    }

    private function ownNotification(User $user, string $notificationId): Notification
    {
        $notification = $this->findNotification->handle($user, $notificationId);
        Gate::authorize('update', $notification);

        return $notification;
    }
}
