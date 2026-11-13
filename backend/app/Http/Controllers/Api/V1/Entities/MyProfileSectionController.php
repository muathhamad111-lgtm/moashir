<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\SaveOwnProfileSection;
use App\Data\Entities\ProfileSectionSaved;
use App\Http\Requests\Entities\UpdateClassificationSectionRequest;
use App\Http\Requests\Entities\UpdateContactSectionRequest;
use App\Http\Requests\Entities\UpdateGovernanceSectionRequest;
use App\Http\Requests\Entities\UpdateIdentitySectionRequest;
use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyProfileSectionController
{
    public function identity(UpdateIdentitySectionRequest $request, #[CurrentUser] User $owner, SaveOwnProfileSection $save): JsonResponse
    {
        return $this->saved($save->handle($owner, $request->update()));
    }

    public function classification(UpdateClassificationSectionRequest $request, #[CurrentUser] User $owner, SaveOwnProfileSection $save): JsonResponse
    {
        return $this->saved($save->handle($owner, $request->update()));
    }

    public function governance(UpdateGovernanceSectionRequest $request, #[CurrentUser] User $owner, SaveOwnProfileSection $save): JsonResponse
    {
        return $this->saved($save->handle($owner, $request->update()));
    }

    public function contact(UpdateContactSectionRequest $request, #[CurrentUser] User $owner, SaveOwnProfileSection $save): JsonResponse
    {
        return $this->saved($save->handle($owner, $request->update()));
    }

    private function saved(ProfileSectionSaved $saved): JsonResponse
    {
        return ApiResponse::resource(new OwnEntityResource($saved->entity->load(OwnEntityResource::RELATIONS)), Response::HTTP_OK, [
            'section' => $saved->section->value,
            'changed_fields' => $saved->changedFields,
        ]);
    }
}
