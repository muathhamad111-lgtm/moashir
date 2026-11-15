<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Services\Identity\UserAvatarIntake;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use LogicException;

final class UploadAvatarRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minimum = config()->integer('moashir.users.avatar.min_side_px');
        $maximum = config()->integer('moashir.users.avatar.max_side_px');

        return [
            UserAvatarIntake::FIELD => [
                'required',
                'file',
                'extensions:png,jpg,jpeg',
                'mimes:png,jpg,jpeg',
                'max:'.config()->integer('moashir.users.avatar.max_kb'),
                Rule::dimensions()->minWidth($minimum)->minHeight($minimum)->maxWidth($maximum)->maxHeight($maximum),
            ],
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file(UserAvatarIntake::FIELD);

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The photo is validated before it is read.');
        }

        return $file;
    }
}
