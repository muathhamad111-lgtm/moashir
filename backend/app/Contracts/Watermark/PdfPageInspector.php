<?php

declare(strict_types=1);

namespace App\Contracts\Watermark;

use App\Support\ValueObjects\PageSize;

interface PdfPageInspector
{
    /**
     * @return list<PageSize>
     */
    public function visibleSizesOf(string $path): array;
}
