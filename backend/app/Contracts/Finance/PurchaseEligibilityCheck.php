<?php

declare(strict_types=1);

namespace App\Contracts\Finance;

use App\Data\Finance\OrderTerms;
use App\Models\Entities\Entity;

interface PurchaseEligibilityCheck
{
    public function assertMayPurchase(Entity $entity, OrderTerms $terms): void;
}
