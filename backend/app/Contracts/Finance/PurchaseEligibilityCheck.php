<?php

declare(strict_types=1);

namespace App\Contracts\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Models\Entities\Entity;

interface PurchaseEligibilityCheck
{
    public function assertMayPurchase(Entity $entity, PaymentPurpose $purpose): void;
}
