<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\GetNdaOverview;
use App\Actions\Nda\UpdateNdaSettings;
use App\Http\Requests\Nda\UpdateNdaSettingsRequest;
use App\Http\Resources\Nda\NdaOverviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class NdaSettingsController
{
    public function show(GetNdaOverview $overview): JsonResponse
    {
        return ApiResponse::resource(new NdaOverviewResource($overview->handle()));
    }

    public function update(UpdateNdaSettingsRequest $request, #[CurrentUser] User $actor, UpdateNdaSettings $update, GetNdaOverview $overview): JsonResponse
    {
        $update->handle($actor, $request->update());

        return ApiResponse::resource(new NdaOverviewResource($overview->handle()));
    }
}
