<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\CreateCmsSectionItem;
use App\Actions\Cms\RemoveCmsSectionItem;
use App\Actions\Cms\UpdateCmsSectionItem;
use App\Http\Requests\Cms\StoreCmsItemRequest;
use App\Http\Requests\Cms\UpdateCmsItemRequest;
use App\Http\Resources\Cms\CmsSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CmsSectionItemController
{
    public function store(StoreCmsItemRequest $request, CmsSection $section, #[CurrentUser] User $actor, CreateCmsSectionItem $create, CmsSectionLoader $loader): JsonResponse
    {
        $create->handle($actor, $section, $request->item());

        return ApiResponse::resource(new CmsSectionResource($loader->load($section->id)), Response::HTTP_CREATED);
    }

    public function update(UpdateCmsItemRequest $request, CmsSection $section, CmsSectionItem $item, #[CurrentUser] User $actor, UpdateCmsSectionItem $update, CmsSectionLoader $loader): JsonResponse
    {
        $update->handle($actor, $section, $item, $request->changes());

        return ApiResponse::resource(new CmsSectionResource($loader->load($section->id)));
    }

    public function destroy(CmsSection $section, CmsSectionItem $item, #[CurrentUser] User $actor, RemoveCmsSectionItem $remove): Response
    {
        $remove->handle($actor, $section, $item);

        return ApiResponse::noContent();
    }
}
