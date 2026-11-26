<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Contracts\Watermark\PdfOverlay;
use App\Contracts\Watermark\PdfPageInspector;
use App\Data\Watermark\ViewerStamp;

final readonly class PdfStamper
{
    public function __construct(
        private PdfPageInspector $inspector,
        private StampSheetRenderer $renderer,
        private PdfOverlay $overlay,
    ) {}

    public function stamp(string $source, ViewerStamp $stamp, string $stampPath, string $output): void
    {
        $sheets = [];
        $pagesBySheet = [];

        foreach ($this->inspector->visibleSizesOf($source) as $index => $size) {
            $key = $size->key();
            $sheets[$key] ??= $size;
            $pagesBySheet[$key][] = $index + 1;
        }

        file_put_contents($stampPath, $this->renderer->render($stamp, array_values($sheets)));
        $this->overlay->overlay($source, $stampPath, array_values($pagesBySheet), $output);
    }
}
