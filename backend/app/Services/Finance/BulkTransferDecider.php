<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\BulkDecisionOutcome;
use App\Exceptions\ApiException;
use App\Models\Finance\Payment;
use Closure;

final class BulkTransferDecider
{
    private const string NOT_FOUND = 'not_found';

    /**
     * @param  list<string>  $paymentIds
     * @param  Closure(Payment): Payment  $decide
     */
    public function decideEach(array $paymentIds, Closure $decide): BulkDecisionOutcome
    {
        $payments = Payment::query()->bankTransfers()->whereIn('public_id', $paymentIds)->get()->keyBy('public_id');
        $decided = [];
        $skipped = [];

        foreach ($paymentIds as $paymentId) {
            $payment = $payments->get($paymentId);

            if (! $payment instanceof Payment) {
                $skipped[] = ['id' => $paymentId, 'code' => self::NOT_FOUND];

                continue;
            }

            try {
                $decide($payment);
                $decided[] = $paymentId;
            } catch (ApiException $refusal) {
                $skipped[] = ['id' => $paymentId, 'code' => $refusal->error()->code];
            }
        }

        return new BulkDecisionOutcome($decided, $skipped);
    }
}
