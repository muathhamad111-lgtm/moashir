<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\EvidenceUpload;
use App\Services\Files\FileSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UploadEvidenceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.app(FileSettings::class)->maxUploadKilobytes()],
            'sub_criterion_id' => ['required', 'integer', 'min:1'],
            'attachment_key' => ['nullable', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function upload(): EvidenceUpload
    {
        $file = $this->file('file');

        return new EvidenceUpload(
            $file instanceof UploadedFile ? $file : throw new LogicException('The validated upload is missing.'),
            $this->integer('sub_criterion_id'),
            $this->filled('attachment_key') ? $this->string('attachment_key')->trim()->toString() : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
