<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use Illuminate\Http\UploadedFile;

final readonly class EvidenceUpload
{
    public function __construct(public UploadedFile $file, public int $subId, public ?string $attachmentKey, public ?string $reason) {}
}
