<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Settings\PaletteColor;
use App\Models\Settings\BrandingTheme;
use App\Support\Files\PublicAssetLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BrandingTheme $resource
 */
final class BrandingThemeResource extends JsonResource
{
    /**
     * @param  list<PaletteColor>  $palette
     */
    public function __construct(BrandingTheme $theme, private readonly array $palette)
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
            'accent_token' => $theme->accent_token->value,
            'logo_light' => PublicAssetLink::of($theme->logoLight),
            'logo_dark' => PublicAssetLink::of($theme->logoDark),
            'palette' => array_map(static fn (PaletteColor $color): array => [
                'token' => $color->token->value,
                'hex' => $color->fill,
                'text_on_light_hex' => $color->textOnLight,
                'label' => ['ar' => __('branding.palette.'.$color->token->value, [], 'ar'), 'en' => __('branding.palette.'.$color->token->value, [], 'en')],
            ], $this->palette),
            'updated_at' => $theme->updated_at->toIso8601String(),
        ];
    }
}
