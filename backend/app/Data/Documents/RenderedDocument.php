<?php

declare(strict_types=1);

namespace App\Data\Documents;

final readonly class RenderedDocument
{
    public const string PDF_MIME_TYPE = 'application/pdf';

    public function __construct(
        public string $contents,
        public string $fileName,
    ) {}
}
