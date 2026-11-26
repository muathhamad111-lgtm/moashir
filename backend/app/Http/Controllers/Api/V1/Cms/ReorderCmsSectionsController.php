<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\ReorderCmsSections;
use App\Http\Requests\Cms\ReorderCmsContentRequest;
use App\Http\Resources\Cms\CmsSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ReorderCmsSectionsController
{
    public function __invoke(ReorderCmsContentRequest $request, #[CurrentUser] User $actor, ReorderCmsSections $reorder, CmsSectionLoader $loader): JsonResponse
    {
        $page = $reorder->handle($actor, $request->orderedIds());

        return ApiResponse::data(CmsSectionResource::collection($loader->ofPage($page))->resolve($request));
    }
}
