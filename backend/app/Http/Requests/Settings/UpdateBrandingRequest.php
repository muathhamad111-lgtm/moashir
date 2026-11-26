<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\BrandColorOverrides;
use App\Data\Settings\BrandColors;
use App\Data\Settings\BrandRailColors;
use App\Enums\Settings\DerivedBrandColor;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBrandingRequest extends FormRequest
{
    private const string HEX_RULE = 'regex:/^#[0-9a-fA-F]{6}$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['required', 'string', self::HEX_RULE],
            'secondary_color' => ['required', 'string', self::HEX_RULE],
            'color_overrides' => ['sometimes', 'array:'.implode(',', DerivedBrandColor::keys())],
            'color_overrides.*' => ['required', 'string', self::HEX_RULE],
            // Absent or null: the sidebar colour follows the brand colours.
            'sidebar_top_color' => ['nullable', 'string', self::HEX_RULE],
            'sidebar_bottom_color' => ['nullable', 'string', self::HEX_RULE],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function colors(): BrandColors
    {
        return new BrandColors(
            $this->string('primary_color')->trim()->toString(),
            $this->string('secondary_color')->trim()->toString(),
            new BrandColorOverrides($this->overrides()),
            new BrandRailColors($this->optionalColor('sidebar_top_color'), $this->optionalColor('sidebar_bottom_color')),
        );
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->toString() : null;
    }

    private function optionalColor(string $field): ?string
    {
        return $this->filled($field) ? $this->string($field)->trim()->toString() : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function overrides(): array
    {
        $overrides = $this->validated('color_overrides', []);

        return is_array($overrides) ? $overrides : [];
    }
}
