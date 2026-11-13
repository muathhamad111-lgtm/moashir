<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\CreateCmsSection;
use App\Actions\Cms\RemoveCmsSection;
use App\Actions\Cms\UpdateCmsSection;
use App\Http\Requests\Cms\StoreCmsSectionRequest;
use App\Http\Requests\Cms\UpdateCmsSectionRequest;
use App\Http\Resources\Cms\CmsSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CmsSectionController
{
    public function store(StoreCmsSectionRequest $request, CmsPage $cmsPage, #[CurrentUser] User $actor, CreateCmsSection $create, CmsSectionLoader $loader): JsonResponse
    {
        $section = $create->handle($actor, $cmsPage, $request->section());

        return ApiResponse::resource(new CmsSectionResource($loader->load($section->id)), Response::HTTP_CREATED);
    }

    public function update(UpdateCmsSectionRequest $request, CmsSection $section, #[CurrentUser] User $actor, UpdateCmsSection $update, CmsSectionLoader $loader): JsonResponse
    {
        $update->handle($actor, $section, $request->changes());

        return ApiResponse::resource(new CmsSectionResource($loader->load($section->id)));
    }

    public function destroy(CmsSection $section, #[CurrentUser] User $actor, RemoveCmsSection $remove): Response
    {
        $remove->handle($actor, $section);

        return ApiResponse::noContent();
    }
}
