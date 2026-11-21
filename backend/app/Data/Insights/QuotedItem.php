<?php

declare(strict_types=1);

namespace App\Data\Insights;

final readonly class QuotedItem
{
    public function __construct(public ClassifiedSub $item, public ?string $quote) {}
}
