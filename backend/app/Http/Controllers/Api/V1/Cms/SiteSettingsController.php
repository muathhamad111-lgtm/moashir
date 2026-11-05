<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cms;

use App\Actions\Cms\UpdateSiteSettings;
use App\Http\Requests\Cms\UpdateSiteSettingsRequest;
use App\Http\Resources\Cms\SiteSettingsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cms\SiteSettings;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SiteSettingsController
{
    public function show(): JsonResponse
    {
        return ApiResponse::resource(new SiteSettingsResource(SiteSettings::current()));
    }

    public function update(UpdateSiteSettingsRequest $request, #[CurrentUser] User $actor, UpdateSiteSettings $update): JsonResponse
    {
        $update->handle($actor, $request->changes());

        return ApiResponse::resource(new SiteSettingsResource(SiteSettings::current()));
    }
}
