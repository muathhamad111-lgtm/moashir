<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;

final class PaymentToSettleReader
{
    public function read(Entity $entity): ?Payment
    {
        $latest = Payment::query()->ownedBy($entity->id)->where('purpose', PaymentPurpose::Accredited)->latest('id')->first();

        if (! $latest instanceof Payment) {
            return null;
        }

        $openCardOrder = $latest->channel === PaymentChannel::Electronic && in_array($latest->status, [PaymentStatus::Created, PaymentStatus::Pending], true);

        return $openCardOrder || $latest->status === PaymentStatus::Rejected ? $latest : null;
    }
}
