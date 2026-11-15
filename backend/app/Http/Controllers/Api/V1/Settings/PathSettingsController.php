<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdatePathSettings;
use App\Enums\Assessments\AssessmentPath;
use App\Http\Requests\Settings\UpdatePathSettingsRequest;
use App\Http\Resources\Settings\PathIdentitySettingsResource;
use App\Http\Resources\Settings\PathSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Paths\PathIdentityReader;
use App\Services\Settings\PathSettingsReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

final readonly class PathSettingsController
{
    public function __construct(private PathSettingsReader $settings, private PathIdentityReader $identities) {}

    public function show(AssessmentPath $path): JsonResponse
    {
        return ApiResponse::resource($this->resourceOf($path));
    }

    public function update(UpdatePathSettingsRequest $request, AssessmentPath $path, #[CurrentUser] User $actor, UpdatePathSettings $update): JsonResponse
    {
        $update->handle($actor, $path, $request->toUpdate());

        return ApiResponse::resource($this->resourceOf($path));
    }

    private function resourceOf(AssessmentPath $path): JsonResource
    {
        return $path->isReviewed()
            ? new PathSettingsResource($this->settings->read($path))
            : new PathIdentitySettingsResource($this->identities->of($path));
    }
}
