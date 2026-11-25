<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Actions\Entities\UploadOwnEntityDocument;
use App\Enums\Entities\EntityDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UploadEntityDocumentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            UploadOwnEntityDocument::FIELD => [
                'required',
                'file',
                'extensions:pdf',
                'mimes:pdf',
                'max:'.config()->integer($this->document()->maxKilobytesKey()),
            ],
        ];
    }

    public function document(): EntityDocument
    {
        $document = $this->route('document');

        if (! $document instanceof EntityDocument) {
            throw new LogicException('The document is bound from the route before the request is validated.');
        }

        return $document;
    }

    public function upload(): UploadedFile
    {
        $file = $this->file(UploadOwnEntityDocument::FIELD);

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The entity document is validated before it is read.');
        }

        return $file;
    }
}
