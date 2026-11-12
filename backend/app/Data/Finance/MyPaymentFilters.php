<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;

final readonly class MyPaymentFilters
{
    /**
     * @param  list<PaymentStatus>  $statuses
     */
    public function __construct(
        public array $statuses = [],
        public ?PaymentPurpose $purpose = null,
        public int $perPage = 15,
        public ?SearchTerm $search = null,
        public ?PaymentInstrument $method = null,
        public ?PaymentChannel $channel = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
