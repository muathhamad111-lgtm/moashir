<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\BrandingLogoUpload;
use App\Enums\Settings\LogoVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use LogicException;

final class UploadBrandingLogoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png', 'max:'.config()->integer('moashir.branding.logo_max_kb')],
            'variant' => ['required', Rule::enum(LogoVariant::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function upload(): BrandingLogoUpload
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The logo is validated before it is read.');
        }

        return new BrandingLogoUpload(
            $file,
            $this->enum('variant', LogoVariant::class) ?? LogoVariant::Light,
            $this->filled('reason') ? $this->string('reason')->toString() : null,
        );
    }
}
