<?php

declare(strict_types=1);

use App\Contracts\Watermark\OfficeConverter;
use App\Contracts\Watermark\PdfOverlay;
use App\Contracts\Watermark\PdfPageInspector;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Support\ValueObjects\PageSize;

function slowTool(): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'moashir-slow-tool-');
    file_put_contents($path, "#!/bin/sh\nsleep 5\n");
    chmod($path, 0700);
    test()->beforeApplicationDestroyed(static fn (): bool => unlink($path));

    return $path;
}

function samplePdfPath(): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'moashir-sample-pdf-');
    file_put_contents($path, multiPagePdf([new PageSize(595, 842)]));
    test()->beforeApplicationDestroyed(static fn (): bool => unlink($path));

    return $path;
}

it('fails closed when LibreOffice runs past its time limit', function (): void {
    config(['moashir.watermark.soffice_binary' => slowTool(), 'moashir.watermark.conversion_timeout_seconds' => 1]);
    $directory = sys_get_temp_dir();

    expect(fn (): string => app(OfficeConverter::class)->toPdf(samplePdfPath(), $directory, $directory.'/profile'))
        ->toThrow(WatermarkFailed::class);
});

it('fails closed when qpdf or pdfinfo run past their time limit', function (): void {
    config(['moashir.watermark.qpdf_binary' => slowTool(), 'moashir.watermark.pdfinfo_binary' => slowTool(), 'moashir.watermark.tool_timeout_seconds' => 1]);
    $pdf = samplePdfPath();

    expect(fn (): array => app(PdfPageInspector::class)->visibleSizesOf($pdf))->toThrow(WatermarkFailed::class)
        ->and(fn () => app(PdfOverlay::class)->overlay($pdf, $pdf, [[1]], $pdf.'.out'))->toThrow(WatermarkFailed::class);
});

it('runs the watermark smoke check without touching the database', function (): void {
    $this->artisan('moashir:files:watermark-smoke')
        ->expectsOutputToContain('Converted and stamped 1 page(s)')
        ->assertSuccessful();
});
