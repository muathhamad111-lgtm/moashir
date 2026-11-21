<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Entities\EntityErasureStep;
use App\Enums\Finance\CancellationCause;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;

final readonly class BankTransferEraser implements EntityErasureStep
{
    public function __construct(private PaymentCancellation $cancellation) {}

    public function erase(Entity $entity): void
    {
        $underReview = Payment::query()
            ->ownedBy($entity->id)
            ->bankTransfers()
            ->where('status', PaymentStatus::UnderReview)
            ->with('entity')
            ->lockForUpdate()
            ->get();

        foreach ($underReview as $payment) {
            $this->cancellation->cancel($payment, CancellationCause::EntityErased);
        }
    }
}
