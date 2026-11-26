<?php

declare(strict_types=1);

namespace App\Contracts\Finance;

use App\Data\Finance\OpenedRequest;

interface OpenedRequestLookup
{
    /**
     * @param  list<int>  $paymentIds
     * @return array<int, OpenedRequest>
     */
    public function forPayments(array $paymentIds): array;
}
