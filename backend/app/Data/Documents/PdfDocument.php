<?php

declare(strict_types=1);

namespace App\Data\Documents;

final readonly class PdfDocument
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $template,
        public array $data,
        public ?string $watermark = null,
    ) {}
}
