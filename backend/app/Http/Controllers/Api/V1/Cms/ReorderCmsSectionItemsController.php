<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\ReorderCmsSectionItems;
use App\Http\Requests\Cms\ReorderCmsContentRequest;
use App\Http\Resources\Cms\CmsSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ReorderCmsSectionItemsController
{
    public function __invoke(ReorderCmsContentRequest $request, CmsSection $section, #[CurrentUser] User $actor, ReorderCmsSectionItems $reorder, CmsSectionLoader $loader): JsonResponse
    {
        $reorder->handle($actor, $section, $request->orderedIds());

        return ApiResponse::resource(new CmsSectionResource($loader->load($section->id)));
    }
}
