<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdatePathSettings;
use App\Enums\Assessments\AssessmentPath;
use App\Http\Requests\Settings\UpdatePathSettingsRequest;
use App\Http\Resources\Settings\PathSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Settings\PathSettingsReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PathSettingsController
{
    public function show(AssessmentPath $path, PathSettingsReader $reader): JsonResponse
    {
        return ApiResponse::resource(new PathSettingsResource($reader->read($path)));
    }

    public function update(UpdatePathSettingsRequest $request, AssessmentPath $path, #[CurrentUser] User $actor, UpdatePathSettings $update, PathSettingsReader $reader): JsonResponse
    {
        $update->handle($actor, $path, $request->toUpdate());

        return ApiResponse::resource(new PathSettingsResource($reader->read($path)));
    }
}
