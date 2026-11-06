<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Actions\Notifications\ListNotificationTypes;
use App\Http\Requests\Notifications\ListNotificationTypesRequest;
use App\Http\Resources\Notifications\NotificationTypeResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class NotificationTypeController
{
    public function index(ListNotificationTypesRequest $request, ListNotificationTypes $listTypes): JsonResponse
    {
        $types = $listTypes->handle($request->filters());

        return ApiResponse::data(NotificationTypeResource::collection($types)->resolve($request), meta: ['total' => count($types)]);
    }
}
