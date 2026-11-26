<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Platform\DemoPurgeCount;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Support\Demo\DemoPurgeTally;
use App\Support\ScalarValue;

final readonly class DemoPaymentPurger
{
    public function __construct(private DemoFiles $files) {}

    /**
     * @return list<int>
     */
    public function purgeOf(int $entityId, DemoPurgeTally $tally): array
    {
        CheckoutIntent::query()->where('entity_id', $entityId)->toBase()->delete();
        $paymentIds = array_values(array_map(
            static fn (mixed $id): int => ScalarValue::int($id),
            Payment::query()->ownedBy($entityId)->pluck('id')->all(),
        ));

        if ($paymentIds === []) {
            return [];
        }

        $fileIds = $this->files->ofPayments($paymentIds);
        PaymentReceipt::query()->whereIn('payment_id', $paymentIds)->toBase()->delete();
        Refund::query()->whereIn('payment_id', $paymentIds)->toBase()->delete();
        BankTransfer::query()->whereIn('payment_id', $paymentIds)->toBase()->delete();
        PaymentEvent::query()->whereIn('payment_id', $paymentIds)->toBase()->delete();
        $tally->add(DemoPurgeCount::Payments, ScalarValue::int(Payment::query()->whereKey($paymentIds)->toBase()->delete()));

        return $fileIds;
    }
}
