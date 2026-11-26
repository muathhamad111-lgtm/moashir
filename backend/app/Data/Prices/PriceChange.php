<?php

declare(strict_types=1);

namespace App\Data\Prices;

use App\Enums\Assessments\PathAvailability;
use App\Support\ValueObjects\Money;
use Carbon\CarbonImmutable;

final readonly class PriceChange
{
    public function __construct(
        public Money $price,
        public PathAvailability $availability,
        public ?CarbonImmutable $effectiveFrom,
        public ?int $baseVersionId,
        public ?string $reason,
    ) {}

    public function isImmediate(): bool
    {
        return ! $this->effectiveFrom instanceof CarbonImmutable;
    }
}
