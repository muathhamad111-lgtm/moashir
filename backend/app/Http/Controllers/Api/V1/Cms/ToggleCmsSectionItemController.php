<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\ToggleCmsSectionItem;
use App\Http\Requests\Cms\ToggleCmsContentRequest;
use App\Http\Resources\Cms\CmsSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ToggleCmsSectionItemController
{
    public function __invoke(ToggleCmsContentRequest $request, CmsSection $section, CmsSectionItem $item, #[CurrentUser] User $actor, ToggleCmsSectionItem $toggle, CmsSectionLoader $loader): JsonResponse
    {
        $toggle->handle($actor, $section, $item, $request->enabled());

        return ApiResponse::resource(new CmsSectionResource($loader->load($section->id)));
    }
}
