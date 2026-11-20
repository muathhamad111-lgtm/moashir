<?php

declare(strict_types=1);

namespace App\Data\Watermark;

use App\Enums\Identity\Locale;
use App\Support\ValueObjects\NationalId;
use App\Support\ValueObjects\TraceCode;
use Carbon\CarbonImmutable;

final readonly class ViewerStamp
{
    public function __construct(
        public string $viewerName,
        public ?NationalId $nationalId,
        public TraceCode $traceCode,
        public CarbonImmutable $stampedAt,
        public Locale $locale,
    ) {}

    public function identityLine(): string
    {
        return $this->nationalId instanceof NationalId ? $this->viewerName.' · '.$this->nationalId->value : $this->viewerName;
    }
}
