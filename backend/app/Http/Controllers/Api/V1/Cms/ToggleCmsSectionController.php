<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\ToggleCmsSection;
use App\Http\Requests\Cms\ToggleCmsContentRequest;
use App\Http\Resources\Cms\CmsSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsSectionLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ToggleCmsSectionController
{
    public function __invoke(ToggleCmsContentRequest $request, CmsSection $section, #[CurrentUser] User $actor, ToggleCmsSection $toggle, CmsSectionLoader $loader): JsonResponse
    {
        $toggle->handle($actor, $section, $request->enabled());

        return ApiResponse::resource(new CmsSectionResource($loader->load($section->id)));
    }
}
