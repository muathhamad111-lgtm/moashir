<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\ReplaceBrandingLogo;
use App\Actions\Settings\ResetBranding;
use App\Actions\Settings\UpdateBrandingColors;
use App\Http\Requests\Settings\ResetBrandingRequest;
use App\Http\Requests\Settings\UpdateBrandingRequest;
use App\Http\Requests\Settings\UploadBrandingLogoRequest;
use App\Http\Resources\Files\PublicImageResource;
use App\Http\Resources\Settings\BrandingThemeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Settings\BrandColorDeriver;
use App\Services\Settings\DefaultBrandColors;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class BrandingController
{
    public function __construct(private BrandColorDeriver $deriver, private DefaultBrandColors $defaults) {}

    public function show(): JsonResponse
    {
        return $this->theme();
    }

    public function update(UpdateBrandingRequest $request, #[CurrentUser] User $actor, UpdateBrandingColors $update): JsonResponse
    {
        $update->handle($actor, $request->colors(), $request->reason());

        return $this->theme();
    }

    public function reset(ResetBrandingRequest $request, #[CurrentUser] User $actor, ResetBranding $reset): JsonResponse
    {
        $reset->handle($actor, $request->reason());

        return $this->theme();
    }

    public function logo(UploadBrandingLogoRequest $request, #[CurrentUser] User $actor, ReplaceBrandingLogo $replace): JsonResponse
    {
        return ApiResponse::resource(new PublicImageResource($replace->handle($actor, $request->upload())));
    }

    private function theme(): JsonResponse
    {
        $theme = BrandingTheme::current();

        return ApiResponse::resource(new BrandingThemeResource($theme, $this->deriver->scheme($theme->colors()), $this->defaults->get()));
    }
}
