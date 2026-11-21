<?php

declare(strict_types=1);

namespace App\Data\Documents;

use App\Enums\Documents\DocumentWatermark;

final readonly class DocumentPage
{
    public function __construct(
        public string $locale,
        public string $direction,
        public ?DocumentWatermark $watermark,
    ) {}

    public static function of(PdfDocument $document): self
    {
        return new self($document->locale, $document->direction(), DocumentWatermark::tryFrom((string) $document->watermark));
    }
}
