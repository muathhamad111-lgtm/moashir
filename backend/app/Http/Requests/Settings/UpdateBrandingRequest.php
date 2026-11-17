<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\BrandColors;
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
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function colors(): BrandColors
    {
        return new BrandColors(
            $this->string('primary_color')->trim()->toString(),
            $this->string('secondary_color')->trim()->toString(),
        );
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->toString() : null;
    }
}
