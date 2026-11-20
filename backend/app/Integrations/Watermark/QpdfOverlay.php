<?php

declare(strict_types=1);

namespace App\Integrations\Watermark;

use App\Contracts\Watermark\PdfOverlay;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Support\Watermark\PageRanges;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory as ProcessFactory;

final readonly class QpdfOverlay implements PdfOverlay
{
    private const array SUCCESS_CODES = [0, 3];

    public function __construct(private ProcessFactory $processes, private Repository $config) {}

    public function overlay(string $source, string $stamp, array $pagesBySheet, string $output): void
    {
        $command = [$this->config->string('moashir.watermark.qpdf_binary'), $source];

        foreach ($pagesBySheet as $sheet => $pages) {
            $command[] = '--overlay';
            $command[] = $stamp;
            $command[] = '--to='.PageRanges::of($pages);
            $command[] = '--from=';
            $command[] = '--repeat='.($sheet + 1);
            $command[] = '--';
        }

        $command[] = $output;
        try {
            $result = $this->processes->newPendingProcess()->timeout($this->config->integer('moashir.watermark.tool_timeout_seconds'))->run($command);
        } catch (ProcessTimedOutException) {
            throw WatermarkFailed::unreadable();
        }

        if (! in_array($result->exitCode(), self::SUCCESS_CODES, true) || ! is_file($output)) {
            throw WatermarkFailed::unreadable();
        }
    }
}
