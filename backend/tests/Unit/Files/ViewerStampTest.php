<?php

declare(strict_types=1);

use App\Data\Watermark\StampPlacement;
use App\Data\Watermark\ViewerStamp;
use App\Enums\Identity\Locale;
use App\Support\ValueObjects\NationalId;
use App\Support\ValueObjects\PageSize;
use App\Support\ValueObjects\TraceCode;
use Carbon\CarbonImmutable;

function viewerStampAt(string $utc, ?string $nationalId = '1234567890'): ViewerStamp
{
    $id = $nationalId === null ? null : new NationalId($nationalId);

    return new ViewerStamp('Fahad Al-Qahtani', $id, TraceCode::generate(), CarbonImmutable::parse($utc, 'UTC'), Locale::Arabic);
}

it('prints the national ID, then the date and the time in Riyadh', function (): void {
    expect(viewerStampAt('2026-11-10 07:43:00')->line())->toBe('1234567890 - 10/11/2026 - 10:43am');
});

it('moves to the next day in Riyadh and writes the hour without a leading zero', function (): void {
    expect(viewerStampAt('2026-11-10 21:05:00')->line())->toBe('1234567890 - 11/11/2026 - 12:05am')
        ->and(viewerStampAt('2026-11-10 18:09:00')->line())->toBe('1234567890 - 10/11/2026 - 9:09pm');
});

it('prints the name in place of the national ID when none is recorded', function (): void {
    expect(viewerStampAt('2026-11-10 07:43:00', null)->line())->toBe('Fahad Al-Qahtani - 10/11/2026 - 10:43am');
});

it('lays the line along the diagonal from the bottom-left to the top-right inside the margins', function (PageSize $page, float $angle): void {
    $line = '1234567890 - 10/11/2026 - 10:43am';
    $placement = StampPlacement::on($page, $line);
    $margin = min($page->width, $page->height) * 0.08;
    $innerDiagonal = hypot($page->width - 2 * $margin, $page->height - 2 * $margin);

    expect($placement->angle)->toBe($angle)
        ->and($placement->centerX)->toBe(round($page->width / 2, 2))
        ->and($placement->centerY)->toBe(round($page->height / 2, 2))
        ->and($placement->length)->toBeLessThanOrEqual($innerDiagonal * 0.82 + 0.01)
        ->and($placement->fontSize)->toBeLessThanOrEqual(min($page->width, $page->height) * 0.07 + 0.01);
})->with([
    'portrait A4' => [new PageSize(595.28, 841.89), 56.19],
    'landscape A4' => [new PageSize(841.89, 595.28), 33.81],
    'square image' => [new PageSize(300, 300), 45.0],
]);

it('keeps a short line at a readable size instead of stretching it across the page', function (): void {
    $placement = StampPlacement::on(new PageSize(595.28, 841.89), 'Ali - 10/11/2026 - 9:05am');

    expect($placement->fontSize)->toBe(round(595.28 * 0.07, 2))
        ->and($placement->length)->toBeLessThan(hypot(595.28, 841.89) * 0.82);
});

it('isolates the name for printing so the date and time keep their order after an Arabic name', function (): void {
    $stamp = new ViewerStamp('فهد القحطاني', null, TraceCode::generate(), CarbonImmutable::parse('2026-11-10 07:43:00', 'UTC'), Locale::Arabic);

    expect($stamp->printedLine())->toBe("\u{2066}\u{2068}فهد القحطاني\u{2069} - 10/11/2026 - 10:43am\u{2069}");
});
