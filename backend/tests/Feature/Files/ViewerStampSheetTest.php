<?php

declare(strict_types=1);

use App\Contracts\Documents\StampPrinter;
use App\Data\Watermark\StampSheet;
use App\Data\Watermark\ViewerStamp;
use App\Enums\Identity\Locale;
use App\Services\Watermark\StampSheetRenderer;
use App\Support\ValueObjects\NationalId;
use App\Support\ValueObjects\PageSize;
use App\Support\ValueObjects\TraceCode;
use Carbon\CarbonImmutable;

final class RecordingStampPrinter implements StampPrinter
{
    public ?StampSheet $sheet = null;

    public function print(StampSheet $sheet): string
    {
        $this->sheet = $sheet;

        return '%PDF-1.4';
    }
}

it('prints one diagonal line per page with no tiling, no footer and no trace code', function (): void {
    $printer = new RecordingStampPrinter;
    app()->instance(StampPrinter::class, $printer);
    $trace = TraceCode::generate();
    $stamp = new ViewerStamp('Fahad Al-Qahtani', new NationalId('1234567890'), $trace, CarbonImmutable::parse('2026-11-10 07:43:00', 'UTC'), Locale::Arabic);

    app(StampSheetRenderer::class)->render($stamp, [new PageSize(595.28, 841.89), new PageSize(841.89, 595.28)]);

    $html = (string) $printer->sheet?->html;
    expect(substr_count($html, '<text '))->toBe(2)
        ->and(substr_count($html, $stamp->printedLine()))->toBe(2)
        ->and($html)->toContain('rotate(-56.19 ')->toContain('rotate(-33.81 ')
        ->and($html)->not->toContain($trace->value)
        ->and($html)->not->toContain('Fahad Al-Qahtani')
        ->and($html)->not->toContain('class="footer"')
        ->and($html)->not->toContain('class="tiles"')
        ->and($printer->sheet?->caption)->toBe('1234567890 - 10/11/2026 - 10:43am');
});
