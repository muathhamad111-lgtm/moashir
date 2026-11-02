<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdateAssessmentSettings;
use App\Http\Requests\Settings\UpdateAssessmentSettingsRequest;
use App\Http\Resources\Settings\AssessmentSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Settings\AssessmentSettingsReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessmentSettingsController
{
    public function show(AssessmentSettingsReader $reader): JsonResponse
    {
        return ApiResponse::resource(new AssessmentSettingsResource($reader->read()));
    }

    public function update(UpdateAssessmentSettingsRequest $request, #[CurrentUser] User $actor, UpdateAssessmentSettings $update, AssessmentSettingsReader $reader): JsonResponse
    {
        $update->handle($actor, $request->toUpdate());

        return ApiResponse::resource(new AssessmentSettingsResource($reader->read()));
    }
}
