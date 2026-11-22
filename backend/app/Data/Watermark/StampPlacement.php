<?php

declare(strict_types=1);

namespace App\Data\Watermark;

use App\Support\ValueObjects\PageSize;

/**
 * Where the single viewer line sits on one page: centred on the diagonal from the bottom-left to the top-right
 * corner of the area inside the margins, sized so the line fits that diagonal (operator decision, 2026-11-22).
 */
final readonly class StampPlacement
{
    private const float MARGIN_RATIO = 0.08;

    private const float DIAGONAL_SHARE = 0.82;

    private const float GLYPH_WIDTH_RATIO = 0.55;

    private const float MAX_FONT_RATIO = 0.07;

    private const int PRECISION = 2;

    private function __construct(
        public float $centerX,
        public float $centerY,
        public float $angle,
        public float $fontSize,
        public float $length,
    ) {}

    public static function on(PageSize $page, string $line): self
    {
        $margin = min($page->width, $page->height) * self::MARGIN_RATIO;
        $innerWidth = $page->width - 2 * $margin;
        $innerHeight = $page->height - 2 * $margin;
        $characters = max(1, mb_strlen($line));
        $fontSize = min(
            hypot($innerWidth, $innerHeight) * self::DIAGONAL_SHARE / ($characters * self::GLYPH_WIDTH_RATIO),
            min($page->width, $page->height) * self::MAX_FONT_RATIO,
        );

        return new self(
            round($page->width / 2, self::PRECISION),
            round($page->height / 2, self::PRECISION),
            round(rad2deg(atan2($innerHeight, $innerWidth)), self::PRECISION),
            round($fontSize, self::PRECISION),
            round($characters * self::GLYPH_WIDTH_RATIO * $fontSize, self::PRECISION),
        );
    }
}
