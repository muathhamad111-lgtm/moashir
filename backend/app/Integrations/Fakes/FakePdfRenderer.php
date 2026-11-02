<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Documents\PdfRenderer;
use App\Data\Documents\PdfDocument;

final class FakePdfRenderer implements PdfRenderer
{
    /**
     * @var list<PdfDocument>
     */
    private array $rendered = [];

    public function render(PdfDocument $document): string
    {
        $this->rendered[] = $document;

        return "%PDF-1.4\n%moashir-fake-renderer\n".json_encode([
            'template' => $document->template,
            'watermark' => $document->watermark,
            'data' => $document->data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n%%EOF\n";
    }

    /**
     * @return list<PdfDocument>
     */
    public function rendered(): array
    {
        return $this->rendered;
    }
}
