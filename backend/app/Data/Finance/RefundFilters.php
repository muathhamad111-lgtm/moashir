<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentPurpose;
use Carbon\CarbonImmutable;

final readonly class RefundFilters
{
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?PaymentPurpose $purpose = null,
        public int $perPage = 15,
    ) {}
}
