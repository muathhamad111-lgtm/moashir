<?php

declare(strict_types=1);

namespace App\Data\Nda;

use App\Support\ValueObjects\LocalizedText;

final readonly class NdaPublication
{
    public function __construct(
        public LocalizedText $changeSummary,
        public string $reason,
    ) {}
}
