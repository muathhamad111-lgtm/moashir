<?php

declare(strict_types=1);

namespace App\Data\Watermark;

use App\Enums\Identity\Locale;
use App\Support\ValueObjects\NationalId;
use App\Support\ValueObjects\TraceCode;
use Carbon\CarbonImmutable;

final readonly class ViewerStamp
{
    private const string DISPLAY_TIMEZONE = 'Asia/Riyadh';

    private const string TIME_FORMAT = 'd/m/Y - g:ia';

    private const string SEPARATOR = ' - ';

    private const string LEFT_TO_RIGHT_ISOLATE = "\u{2066}";

    private const string FIRST_STRONG_ISOLATE = "\u{2068}";

    private const string POP_DIRECTIONAL_ISOLATE = "\u{2069}";

    public function __construct(
        public string $viewerName,
        public ?NationalId $nationalId,
        public TraceCode $traceCode,
        public CarbonImmutable $stampedAt,
        public Locale $locale,
    ) {}

    /**
     * The one line printed on every page: the national ID (the name when none is recorded), then the date and the
     * time in Riyadh, for example "1234567890 - 10/11/2026 - 10:43am" (operator decision, 2026-11-22).
     */
    public function line(): string
    {
        return $this->holder().self::SEPARATOR.$this->moment();
    }

    /**
     * The same line for printing: read left to right, with an Arabic name isolated so the date and time that follow
     * keep their order instead of joining the name's right-to-left run.
     */
    public function printedLine(): string
    {
        $holder = self::FIRST_STRONG_ISOLATE.$this->holder().self::POP_DIRECTIONAL_ISOLATE;

        return self::LEFT_TO_RIGHT_ISOLATE.$holder.self::SEPARATOR.$this->moment().self::POP_DIRECTIONAL_ISOLATE;
    }

    private function holder(): string
    {
        return $this->nationalId instanceof NationalId ? $this->nationalId->value : $this->viewerName;
    }

    private function moment(): string
    {
        return $this->stampedAt->setTimezone(self::DISPLAY_TIMEZONE)->format(self::TIME_FORMAT);
    }
}
