<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\UpdateCmsPageDetails;
use App\Http\Requests\Cms\UpdateCmsPageRequest;
use App\Http\Resources\Cms\CmsPageResource;
use App\Http\Resources\Cms\CmsPageSummaryResource;
use App\Http\Resources\Cms\SiteSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\CmsPage;
use App\Models\Cms\SiteSettings;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsPageController
{
    public function index(Request $request): JsonResponse
    {
        $pages = CmsPage::query()->orderBy('id')->get();

        return ApiResponse::data(CmsPageSummaryResource::collection($pages)->resolve($request));
    }

    public function home(Request $request, CmsSectionLoader $loader): JsonResponse
    {
        $page = CmsPage::query()->where('key', 'home')->firstOrFail();
        $sections = $loader->ofPage($page);

        return ApiResponse::data([
            ...new CmsPageResource($page, $sections)->resolve($request),
            'site_settings' => new SiteSettingsResource(SiteSettings::current())->resolve($request),
        ], meta: ['total' => $sections->count()]);
    }

    public function show(CmsPage $cmsPage, CmsSectionLoader $loader): JsonResponse
    {
        return ApiResponse::resource(new CmsPageResource($cmsPage, $loader->ofPage($cmsPage)));
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $cmsPage, #[CurrentUser] User $actor, UpdateCmsPageDetails $update, CmsSectionLoader $loader): JsonResponse
    {
        $update->handle($actor, $cmsPage, $request->changes());
        $page = $cmsPage->refresh();

        return ApiResponse::resource(new CmsPageResource($page, $loader->ofPage($page)));
    }
}
