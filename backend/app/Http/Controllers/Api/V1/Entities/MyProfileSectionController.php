<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ProposeOwnProfileSection;
use App\Http\Requests\Entities\ProfileSectionRequest;
use App\Http\Requests\Entities\UpdateBillingSectionRequest;
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
    public function identity(UpdateIdentitySectionRequest $request, #[CurrentUser] User $owner, ProposeOwnProfileSection $propose): JsonResponse
    {
        return $this->proposed($request, $owner, $propose);
    }

    public function classification(UpdateClassificationSectionRequest $request, #[CurrentUser] User $owner, ProposeOwnProfileSection $propose): JsonResponse
    {
        return $this->proposed($request, $owner, $propose);
    }

    public function governance(UpdateGovernanceSectionRequest $request, #[CurrentUser] User $owner, ProposeOwnProfileSection $propose): JsonResponse
    {
        return $this->proposed($request, $owner, $propose);
    }

    public function contact(UpdateContactSectionRequest $request, #[CurrentUser] User $owner, ProposeOwnProfileSection $propose): JsonResponse
    {
        return $this->proposed($request, $owner, $propose);
    }

    public function billing(UpdateBillingSectionRequest $request, #[CurrentUser] User $owner, ProposeOwnProfileSection $propose): JsonResponse
    {
        return $this->proposed($request, $owner, $propose);
    }

    private function proposed(ProfileSectionRequest $request, User $owner, ProposeOwnProfileSection $propose): JsonResponse
    {
        $entity = $propose->handle($owner, $request->update());

        return ApiResponse::resource(new OwnEntityResource($entity->load(OwnEntityResource::RELATIONS)), Response::HTTP_ACCEPTED, [
            'section' => $request->section()->value,
        ]);
    }
}
