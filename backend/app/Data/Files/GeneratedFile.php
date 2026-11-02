<?php

declare(strict_types=1);

namespace App\Data\Files;

use App\Enums\Files\StoredFilePurpose;

final readonly class GeneratedFile
{
    public function __construct(
        public StoredFilePurpose $purpose,
        public string $contents,
        public string $fileName,
        public string $extension,
        public string $mimeType,
    ) {}
}
