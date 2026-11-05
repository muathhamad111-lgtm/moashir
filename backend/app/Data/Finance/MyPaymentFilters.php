<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;

final readonly class MyPaymentFilters
{
    /**
     * @param  list<PaymentStatus>  $statuses
     */
    public function __construct(
        public array $statuses = [],
        public ?PaymentPurpose $purpose = null,
        public int $perPage = 15,
    ) {}
}
