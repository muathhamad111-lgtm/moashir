<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Services\Entities\EntityLogoIntake;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use LogicException;

final class UploadEntityLogoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minimum = config()->integer('moashir.entities.logo.min_side_px');
        $maximum = config()->integer('moashir.entities.logo.max_side_px');

        return [
            EntityLogoIntake::FIELD => [
                'required',
                'file',
                'extensions:png,jpg,jpeg',
                'mimes:png,jpg,jpeg',
                'max:'.config()->integer('moashir.entities.logo.max_kb'),
                Rule::dimensions()->minWidth($minimum)->minHeight($minimum)->maxWidth($maximum)->maxHeight($maximum),
            ],
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file(EntityLogoIntake::FIELD);

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The logo is validated before it is read.');
        }

        return $file;
    }
}
