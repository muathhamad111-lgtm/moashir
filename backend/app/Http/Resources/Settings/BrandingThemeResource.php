<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Settings\BrandColors;
use App\Data\Settings\BrandColorScheme;
use App\Models\Settings\BrandingTheme;
use App\Support\Files\PublicAssetLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BrandingTheme $resource
 */
final class BrandingThemeResource extends JsonResource
{
    public function __construct(BrandingTheme $theme, private readonly BrandColorScheme $scheme, private readonly BrandColors $defaults)
    {
        parent::__construct($theme);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $theme = $this->resource;

        return [
            'primary_color' => $theme->primary_color,
            'secondary_color' => $theme->secondary_color,
            'color_overrides' => (object) $theme->colors()->overrides->toArray(),
            'scheme' => $this->scheme->toArray(),
            'default_colors' => ['primary' => $this->defaults->primary, 'secondary' => $this->defaults->secondary],
            'logo_light' => PublicAssetLink::of($theme->logoLight),
            'logo_dark' => PublicAssetLink::of($theme->logoDark),
            'favicon' => PublicAssetLink::of($theme->favicon),
            'logo_site' => PublicAssetLink::of($theme->siteLogo),
            'updated_at' => $theme->updated_at->toIso8601String(),
        ];
    }
}
