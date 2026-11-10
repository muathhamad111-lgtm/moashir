<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentPurpose;

final readonly class CardOrder
{
    public function __construct(public PaymentPurpose $purpose, public string $idempotencyKey, public ?int $awardCycleId = null) {}
}
