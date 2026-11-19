<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Actions\Entities\UploadOwnVatCertificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UploadVatCertificateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            UploadOwnVatCertificate::FIELD => [
                'required',
                'file',
                'extensions:pdf',
                'mimes:pdf',
                'max:'.config()->integer('moashir.entities.vat_certificate.max_kb'),
            ],
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file(UploadOwnVatCertificate::FIELD);

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The VAT registration certificate is validated before it is read.');
        }

        return $file;
    }
}
