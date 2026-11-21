<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Data\Finance\PriceQuote;
use App\Enums\Assessments\PathAvailability;

final readonly class PathPricing
{
    public function __construct(public ?PriceQuote $price, public bool $isFree, public PathAvailability $availability) {}

    public static function free(): self
    {
        return new self(null, true, PathAvailability::Available);
    }

    public static function unset(): self
    {
        return new self(null, false, PathAvailability::Paused);
    }

    public function isAvailable(): bool
    {
        return $this->availability === PathAvailability::Available;
    }

    public function isHidden(): bool
    {
        return $this->availability === PathAvailability::Hidden;
    }

    public function requiresPayment(): bool
    {
        return $this->price instanceof PriceQuote;
    }
}
