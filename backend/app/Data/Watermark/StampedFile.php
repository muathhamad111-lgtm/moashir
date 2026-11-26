<?php

declare(strict_types=1);

namespace App\Data\Watermark;

final readonly class StampedFile
{
    public function __construct(
        public string $path,
        public string $fileName,
        public string $mimeType,
    ) {}
}
