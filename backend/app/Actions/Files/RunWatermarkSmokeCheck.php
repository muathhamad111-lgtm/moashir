<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Contracts\Watermark\OfficeConverter;
use App\Contracts\Watermark\PdfPageInspector;
use App\Data\Watermark\SmokeCheckResult;
use App\Data\Watermark\ViewerStamp;
use App\Enums\Identity\Locale;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Services\Watermark\PdfStamper;
use App\Services\Watermark\WatermarkWorkspaces;
use App\Support\ValueObjects\TraceCode;
use Carbon\CarbonImmutable;

final readonly class RunWatermarkSmokeCheck
{
    private const string SAMPLE = 'watermark/smoke-check.docx';

    private const string VIEWER = 'Moashir smoke check';

    public function __construct(
        private WatermarkWorkspaces $workspaces,
        private OfficeConverter $converter,
        private PdfStamper $stamper,
        private PdfPageInspector $pages,
    ) {}

    public function handle(): SmokeCheckResult
    {
        $started = microtime(true);
        $workspace = $this->workspaces->open();

        try {
            $source = $workspace->path('smoke-check.docx');
            copy(resource_path(self::SAMPLE), $source);
            $pdf = $this->converter->toPdf($source, $workspace->path(''), $workspace->path('office-profile'));
            $output = $workspace->path('stamped.pdf');
            $this->stamper->stamp($pdf, new ViewerStamp(self::VIEWER, null, TraceCode::generate(), CarbonImmutable::now(), Locale::Arabic), $workspace->path('stamp.pdf'), $output);
            $pages = count($this->pages->visibleSizesOf($output));
        } finally {
            $workspace->close();
        }

        return $pages > 0 ? new SmokeCheckResult($pages, microtime(true) - $started) : throw WatermarkFailed::unreadable();
    }
}
