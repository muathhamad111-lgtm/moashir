<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\ReplaceBrandingLogo;
use App\Actions\Settings\ResetBranding;
use App\Actions\Settings\UpdateBrandingAccent;
use App\Http\Requests\Settings\ResetBrandingRequest;
use App\Http\Requests\Settings\UpdateBrandingRequest;
use App\Http\Requests\Settings\UploadBrandingLogoRequest;
use App\Http\Resources\Files\PublicImageResource;
use App\Http\Resources\Settings\BrandingThemeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Settings\BrandPalette;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class BrandingController
{
    public function show(#[CurrentUser] User $viewer, BrandPalette $palette): JsonResponse
    {
        return $this->theme($viewer, $palette);
    }

    public function update(UpdateBrandingRequest $request, #[CurrentUser] User $actor, UpdateBrandingAccent $update, BrandPalette $palette): JsonResponse
    {
        $update->handle($actor, $request->token(), $request->reason());

        return $this->theme($actor, $palette);
    }

    public function reset(ResetBrandingRequest $request, #[CurrentUser] User $actor, ResetBranding $reset, BrandPalette $palette): JsonResponse
    {
        $reset->handle($actor, $request->reason());

        return $this->theme($actor, $palette);
    }

    public function logo(UploadBrandingLogoRequest $request, #[CurrentUser] User $actor, ReplaceBrandingLogo $replace): JsonResponse
    {
        return ApiResponse::resource(new PublicImageResource($replace->handle($actor, $request->upload())));
    }

    private function theme(User $viewer, BrandPalette $palette): JsonResponse
    {
        $colors = $viewer->can('manage', BrandingTheme::class) ? $palette->colors() : [];

        return ApiResponse::resource(new BrandingThemeResource(BrandingTheme::current(), $colors));
    }
}
