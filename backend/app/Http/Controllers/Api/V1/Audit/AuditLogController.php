<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Actions\Audit\ListAuditLogs;
use App\Http\Requests\Audit\IndexAuditLogsRequest;
use App\Http\Resources\Audit\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AuditLogController
{
    public function index(IndexAuditLogsRequest $request, #[CurrentUser] User $viewer, ListAuditLogs $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($viewer, $request->filters()), AuditLogResource::class);
    }

    public function show(Audit $audit): JsonResponse
    {
        return ApiResponse::resource(new AuditLogResource($audit->load('user')));
    }
}
