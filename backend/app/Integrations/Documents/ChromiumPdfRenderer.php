<?php

declare(strict_types=1);

namespace App\Integrations\Documents;

use App\Contracts\Documents\HtmlPdfPrinter;
use App\Contracts\Documents\PdfRenderer;
use App\Data\Documents\PdfDocument;
use App\Exceptions\Documents\DocumentRenderingFailed;
use App\Services\Documents\DocumentHtmlComposer;
use Illuminate\Cache\Limiters\LimiterTimeoutException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository;

final readonly class ChromiumPdfRenderer implements PdfRenderer
{
    private const int SLOT_SAFETY_FACTOR = 4;

    public function __construct(
        private DocumentHtmlComposer $composer,
        private HtmlPdfPrinter $printer,
        private CacheRepository $cache,
        private Repository $config,
    ) {}

    public function render(PdfDocument $document): string
    {
        $html = $this->composer->compose($document);

        try {
            $pdf = $this->cache->funnel($this->config->string('moashir.documents.render_slot_key'))
                ->limit(1)
                ->releaseAfter($this->slotSeconds())
                ->block($this->config->integer('moashir.documents.render_queue_wait_seconds'))
                ->then(fn (): string => $this->printer->print($html));
        } catch (LimiterTimeoutException) {
            throw DocumentRenderingFailed::busy();
        }

        return is_string($pdf) ? $pdf : throw DocumentRenderingFailed::engineError();
    }

    private function slotSeconds(): int
    {
        return $this->config->integer('moashir.documents.browser_startup_timeout_seconds')
            + self::SLOT_SAFETY_FACTOR * $this->config->integer('moashir.documents.render_timeout_seconds');
    }
}
