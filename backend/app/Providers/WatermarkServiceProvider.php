<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Documents\StampPrinter;
use App\Contracts\Watermark\OfficeConverter;
use App\Contracts\Watermark\PdfOverlay;
use App\Contracts\Watermark\PdfPageInspector;
use App\Contracts\Watermark\StampRasterizer;
use App\Integrations\Documents\ChromiumStampPrinter;
use App\Integrations\Documents\UnavailableStampPrinter;
use App\Integrations\Fakes\FakeStampPrinter;
use App\Integrations\Watermark\LibreOfficeConverter;
use App\Integrations\Watermark\PdfinfoPageInspector;
use App\Integrations\Watermark\PdftocairoRasterizer;
use App\Integrations\Watermark\QpdfOverlay;
use App\Services\Documents\ChromiumAvailability;
use App\Services\Platform\LocalFakeAdapters;
use Illuminate\Support\ServiceProvider;

final class WatermarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdfPageInspector::class, PdfinfoPageInspector::class);
        $this->app->bind(PdfOverlay::class, QpdfOverlay::class);
        $this->app->bind(StampRasterizer::class, PdftocairoRasterizer::class);
        $this->app->bind(OfficeConverter::class, LibreOfficeConverter::class);
        $this->app->singleton(FakeStampPrinter::class);
        $this->app->singleton(StampPrinter::class, fn (): StampPrinter => $this->stampPrinter());
    }

    private function stampPrinter(): StampPrinter
    {
        if ($this->app->make(LocalFakeAdapters::class)->allows('moashir.documents.pdf_renderer')) {
            return $this->app->make(FakeStampPrinter::class);
        }

        return $this->app->make(ChromiumAvailability::class)->isReady()
            ? $this->app->make(ChromiumStampPrinter::class)
            : $this->app->make(UnavailableStampPrinter::class);
    }
}
