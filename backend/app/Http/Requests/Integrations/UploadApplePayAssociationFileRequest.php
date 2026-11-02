<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Rules\PlainTextFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UploadApplePayAssociationFileRequest extends FormRequest
{
    private const int MAX_KILOBYTES = 64;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES, 'mimetypes:text/plain', new PlainTextFile],
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The association file is validated before it is read.');
        }

        return $file;
    }
}
