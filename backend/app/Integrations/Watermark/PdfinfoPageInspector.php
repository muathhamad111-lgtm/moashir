<?php

declare(strict_types=1);

namespace App\Integrations\Watermark;

use App\Contracts\Watermark\PdfPageInspector;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Support\ValueObjects\PageSize;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory as ProcessFactory;

final readonly class PdfinfoPageInspector implements PdfPageInspector
{
    private const string LAST_PAGE = '1000000';

    private const string SIZE_LINE = '/^Page\s+(\d+) size:\s+([\d.]+) x ([\d.]+) pts/m';

    private const string ROTATION_LINE = '/^Page\s+(\d+) rot:\s+(\d+)/m';

    private const array TURNED_ROTATIONS = [90, 270];

    public function __construct(private ProcessFactory $processes, private Repository $config) {}

    public function visibleSizesOf(string $path): array
    {
        try {
            $result = $this->processes->newPendingProcess()->timeout($this->config->integer('moashir.watermark.tool_timeout_seconds'))
                ->run([$this->config->string('moashir.watermark.pdfinfo_binary'), '-f', '1', '-l', self::LAST_PAGE, $path]);
        } catch (ProcessTimedOutException) {
            throw WatermarkFailed::unreadable();
        }

        if (! $result->successful()) {
            throw WatermarkFailed::unreadable();
        }

        return $this->sizesFrom($result->output());
    }

    /**
     * @return list<PageSize>
     */
    private function sizesFrom(string $report): array
    {
        preg_match_all(self::SIZE_LINE, $report, $sizes, PREG_SET_ORDER);
        preg_match_all(self::ROTATION_LINE, $report, $rotations, PREG_SET_ORDER);
        $turned = [];

        foreach ($rotations as [, $page, $degrees]) {
            $turned[(int) $page] = in_array((int) $degrees % 360, self::TURNED_ROTATIONS, true);
        }

        $pages = [];

        foreach ($sizes as [, $page, $width, $height]) {
            $size = new PageSize((float) $width, (float) $height);
            $pages[] = ($turned[(int) $page] ?? false) ? $size->turned() : $size;
        }

        return $pages !== [] ? $pages : throw WatermarkFailed::unreadable();
    }
}
