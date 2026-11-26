<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\TermsKind;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonImmutable;

final readonly class TermsPublication
{
    public function __construct(
        public TermsKind $kind,
        public string $version,
        public LocalizedText $body,
        public CarbonImmutable $effectiveFrom,
        public ?LocalizedText $title = null,
    ) {}
}
