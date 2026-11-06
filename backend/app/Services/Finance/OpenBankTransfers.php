<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Entities\OpenRequestSource;
use App\Data\Entities\OpenEntityRequest;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;

final class OpenBankTransfers implements OpenRequestSource
{
    public function openRequestsOf(Entity $entity): array
    {
        return array_values(Payment::query()
            ->ownedBy($entity->id)
            ->bankTransfers()
            ->where('status', PaymentStatus::UnderReview)
            ->orderBy('id')
            ->get(['id', 'reference', 'purpose', 'status'])
            ->map(static fn (Payment $payment): OpenEntityRequest => new OpenEntityRequest(
                $payment->id,
                $payment->reference,
                $payment->purpose === PaymentPurpose::Bag ? EntityRequestPath::Bag : EntityRequestPath::Accredited,
                $payment->status->value,
            ))
            ->all());
    }
}
