<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Exceptions\Documents\DocumentRenderingFailed;
use App\Exceptions\Watermark\ConversionSlotBusy;
use Closure;
use Illuminate\Cache\Limiters\LimiterTimeoutException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository;

final readonly class WatermarkSlots
{
    private const string STAMP_KEY = 'watermark:stamp';

    private const string CONVERSION_KEY = 'watermark:office';

    private const int CONVERSION_SLOTS = 1;

    private const int SAFETY_SECONDS = 60;

    public function __construct(private CacheRepository $cache, private Repository $config) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public function stamp(Closure $work): mixed
    {
        try {
            return $this->cache->funnel(self::STAMP_KEY)
                ->limit($this->config->integer('moashir.watermark.render_slots'))
                ->releaseAfter(2 * $this->config->integer('moashir.watermark.tool_timeout_seconds') + self::SAFETY_SECONDS)
                ->block($this->config->integer('moashir.documents.render_queue_wait_seconds'))
                ->then($work);
        } catch (LimiterTimeoutException) {
            throw DocumentRenderingFailed::busy();
        }
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public function convert(Closure $work): mixed
    {
        try {
            return $this->cache->funnel(self::CONVERSION_KEY)
                ->limit(self::CONVERSION_SLOTS)
                ->releaseAfter($this->config->integer('moashir.watermark.conversion_timeout_seconds') + self::SAFETY_SECONDS)
                ->block($this->config->integer('moashir.watermark.conversion_slot_wait_seconds'))
                ->then($work);
        } catch (LimiterTimeoutException) {
            throw new ConversionSlotBusy('Another Office conversion is running.');
        }
    }
}
