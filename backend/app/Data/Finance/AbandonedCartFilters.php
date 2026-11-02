<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Support\ValueObjects\SearchTerm;

final readonly class AbandonedCartFilters
{
    public function __construct(
        public ?PaymentPurpose $purpose = null,
        public ?bool $contacted = null,
        public ?SearchTerm $search = null,
        public int $perPage = 15,
    ) {}
}
