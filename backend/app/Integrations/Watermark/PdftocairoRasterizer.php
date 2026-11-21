<?php

declare(strict_types=1);

namespace App\Integrations\Watermark;

use App\Contracts\Watermark\StampRasterizer;
use App\Exceptions\Watermark\WatermarkFailed;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory as ProcessFactory;

final readonly class PdftocairoRasterizer implements StampRasterizer
{
    public function __construct(private ProcessFactory $processes, private Repository $config) {}

    public function rasterize(string $stampPdf, int $width, int $height, string $outputBase): string
    {
        try {
            $result = $this->processes->newPendingProcess()->timeout($this->config->integer('moashir.watermark.tool_timeout_seconds'))->run([
                $this->config->string('moashir.watermark.pdftocairo_binary'),
                '-png', '-transp', '-singlefile',
                '-scale-to-x', (string) $width,
                '-scale-to-y', (string) $height,
                $stampPdf, $outputBase,
            ]);
        } catch (ProcessTimedOutException) {
            throw WatermarkFailed::unreadable();
        }
        $png = $outputBase.'.png';

        if (! $result->successful() || ! is_file($png)) {
            throw WatermarkFailed::unreadable();
        }

        return $png;
    }
}
