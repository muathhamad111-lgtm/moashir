<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Models\Finance\PaymentEvent;
use Illuminate\Support\Collection;

final readonly class TransactionEventLog
{
    /**
     * @param  Collection<int, PaymentEvent>  $events  newest first
     */
    public function __construct(public Collection $events, public int $limit, public bool $hasMore) {}
}
