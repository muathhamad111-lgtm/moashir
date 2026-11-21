<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Contracts\Documents\StampPrinter;
use App\Data\Watermark\StampSheet;
use App\Data\Watermark\ViewerStamp;
use App\Services\Documents\DocumentAssets;
use App\Support\ValueObjects\PageSize;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;

final readonly class StampSheetRenderer
{
    private const string VIEW = 'documents.viewer_stamp';

    private const string DISPLAY_TIMEZONE = 'Asia/Riyadh';

    private const string TIME_FORMAT = 'Y-m-d H:i';

    private const float TILE_AREA_POINTS = 12000.0;

    private const int MAX_TILES = 600;

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
        $previousLocale = $this->translator->getLocale();
        $this->translator->setLocale($stamp->locale->value);

        try {
            $footer = $this->footerOf($stamp);
            $html = $this->views->make(self::VIEW, [
                'locale' => $stamp->locale->value,
                'direction' => $stamp->locale->direction(),
                'sizes' => $sizes,
                'tiles' => array_map($this->tilesFor(...), $sizes),
                'identity' => $stamp->identityLine(),
                'footer' => $footer,
                'assets' => $this->assets,
            ])->render();
        } finally {
            $this->translator->setLocale($previousLocale);
        }

        return $this->printer->print(new StampSheet($html, $sizes, $stamp->identityLine().' '.$footer));
    }

    private function footerOf(ViewerStamp $stamp): string
    {
        return __('documents.viewer_stamp.footer', [
            'viewer' => $stamp->identityLine(),
            'time' => $stamp->stampedAt->setTimezone(self::DISPLAY_TIMEZONE)->format(self::TIME_FORMAT),
            'trace' => $stamp->traceCode->value,
        ]);
    }

    private function tilesFor(PageSize $size): int
    {
        return min(self::MAX_TILES, (int) ceil(4 * $size->width * $size->height / self::TILE_AREA_POINTS));
    }
}
