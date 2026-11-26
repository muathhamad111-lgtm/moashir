<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\TransactionEventLog;
use App\Models\Finance\Payment;

final readonly class ListTransactionEvents
{
    public const int LIMIT = 50;

    /**
     * The raw provider payload, signature data and caller IP are never read here.
     */
    private const array COLUMNS = ['id', 'payment_id', 'source', 'event_type', 'mode', 'processing_result', 'provider_payment_id', 'created_at'];

    public function handle(Payment $payment): TransactionEventLog
    {
        $events = $payment->events()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT + 1)
            ->get(self::COLUMNS);

        return new TransactionEventLog($events->take(self::LIMIT)->values(), self::LIMIT, $events->count() > self::LIMIT);
    }
}
