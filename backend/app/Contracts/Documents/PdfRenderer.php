<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

use App\Data\Documents\PdfDocument;

interface PdfRenderer
{
    public function render(PdfDocument $document): string;
}
