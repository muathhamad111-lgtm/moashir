<?php

declare(strict_types=1);

namespace App\Data\Files;

final readonly class FileDownload
{
    /**
     * @param  resource  $stream
     */
    public function __construct(
        public mixed $stream,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}
