<?php

declare(strict_types=1);

namespace App\Integrations\Documents;

use App\Contracts\Documents\HtmlPdfPrinter;
use App\Contracts\Documents\StampPrinter;
use App\Data\Watermark\StampSheet;
use App\Exceptions\Documents\DocumentRenderingFailed;
use Illuminate\Cache\Limiters\LimiterTimeoutException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository;

final readonly class ChromiumStampPrinter implements StampPrinter
{
    private const int SLOT_SAFETY_FACTOR = 2;

    public function __construct(private HtmlPdfPrinter $printer, private CacheRepository $cache, private Repository $config) {}

    public function print(StampSheet $sheet): string
    {
        try {
            $pdf = $this->cache->funnel($this->config->string('moashir.watermark.render_slot_key'))
                ->limit($this->config->integer('moashir.watermark.render_slots'))
                ->releaseAfter($this->slotSeconds())
                ->block($this->config->integer('moashir.documents.render_queue_wait_seconds'))
                ->then(fn (): string => $this->printer->print($sheet->html));
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
