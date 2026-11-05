<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\SetAssessorQualifications;
use App\Http\Requests\Identity\UpdateAssessorQualificationsRequest;
use App\Http\Resources\Identity\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessorQualificationsController
{
    public function __invoke(UpdateAssessorQualificationsRequest $request, User $managedUser, #[CurrentUser] User $actor, SetAssessorQualifications $setQualifications): JsonResponse
    {
        $updated = $setQualifications->handle($actor, $managedUser, $request->qualifications(), $request->reason());

        return ApiResponse::resource(new UserResource($updated->load(User::ADMINISTRATION_RELATIONS)));
    }
}
