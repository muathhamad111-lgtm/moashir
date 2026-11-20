<?php

declare(strict_types=1);

namespace App\Data\Files;

final readonly class UploadCandidate
{
    public function __construct(
        public string $path,
        public string $contents,
        public string $extension,
        public string $mimeType,
    ) {}
}
