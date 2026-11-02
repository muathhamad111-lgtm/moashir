<?php

declare(strict_types=1);

namespace App\Integrations\Documents;

use App\Contracts\Documents\PdfRenderer;
use App\Data\Documents\PdfDocument;
use App\Exceptions\Documents\DocumentRenderingUnavailable;

final class UnavailablePdfRenderer implements PdfRenderer
{
    public function render(PdfDocument $document): string
    {
        throw DocumentRenderingUnavailable::noRenderer();
    }
}
