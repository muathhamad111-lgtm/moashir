<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Contracts\Documents\StampPrinter;
use App\Data\Watermark\StampPlacement;
use App\Data\Watermark\StampSheet;
use App\Data\Watermark\ViewerStamp;
use App\Services\Documents\DocumentAssets;
use App\Support\ValueObjects\PageSize;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;

final readonly class StampSheetRenderer
{
    private const string VIEW = 'documents.viewer_stamp';

    public function __construct(
        private ViewFactory $views,
        private Translator $translator,
        private DocumentAssets $assets,
        private StampPrinter $printer,
    ) {}

    /**
     * @param  list<PageSize>  $sizes
     */
    public function render(ViewerStamp $stamp, array $sizes): string
    {
        $line = $stamp->line();
        $previousLocale = $this->translator->getLocale();
        $this->translator->setLocale($stamp->locale->value);

        try {
            $html = $this->views->make(self::VIEW, [
                'locale' => $stamp->locale->value,
                'direction' => $stamp->locale->direction(),
                'sizes' => $sizes,
                'placements' => array_map(static fn (PageSize $size): StampPlacement => StampPlacement::on($size, $line), $sizes),
                'line' => $stamp->printedLine(),
                'assets' => $this->assets,
            ])->render();
        } finally {
            $this->translator->setLocale($previousLocale);
        }

        return $this->printer->print(new StampSheet($html, $sizes, $line));
    }
}
