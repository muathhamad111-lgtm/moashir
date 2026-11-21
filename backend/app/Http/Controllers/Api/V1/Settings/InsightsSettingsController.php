<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdateInsightsSettings;
use App\Http\Requests\Settings\UpdateInsightsSettingsRequest;
use App\Http\Resources\Settings\InsightsSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Insights\InsightsRulesReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class InsightsSettingsController
{
    public function show(InsightsRulesReader $rules): JsonResponse
    {
        return ApiResponse::resource(new InsightsSettingsResource($rules->read()));
    }

    public function update(UpdateInsightsSettingsRequest $request, #[CurrentUser] User $actor, UpdateInsightsSettings $update, InsightsRulesReader $rules): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new InsightsSettingsResource($rules->read()));
    }
}
