<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\PublishCmsPage;
use App\Actions\Cms\UnpublishCmsPage;
use App\Http\Resources\Cms\CmsPageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CmsPagePublicationController
{
    public function publish(CmsPage $cmsPage, #[CurrentUser] User $actor, PublishCmsPage $publish, CmsSectionLoader $loader): JsonResponse
    {
        $publish->handle($actor, $cmsPage);
        $page = $cmsPage->refresh();

        return ApiResponse::resource(new CmsPageResource($page, $loader->ofPage($page)));
    }

    public function unpublish(CmsPage $cmsPage, #[CurrentUser] User $actor, UnpublishCmsPage $unpublish, CmsSectionLoader $loader): JsonResponse
    {
        $unpublish->handle($actor, $cmsPage);
        $page = $cmsPage->refresh();

        return ApiResponse::resource(new CmsPageResource($page, $loader->ofPage($page)));
    }
}
